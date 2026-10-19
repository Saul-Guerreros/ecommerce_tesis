<?php

namespace App\Services\Venta;

use App\Exceptions\EmisionFacturaException;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\Pedido;
use App\Models\Usuario;
use App\Models\Venta\Caja;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaDetalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Emision del comprobante fiscal a partir de un pedido.
 *
 * Dos puntos de emision, los mismos dos donde ya se descuenta stock
 * definitivo, porque factura y salida de mercaderia representan el mismo
 * hecho economico:
 *
 *   - PagoVerificacionService::cerrarCobro()      (entro la plata)
 *   - PedidoEstadoService, transicion a ENTREGADO (contra entrega y pago
 *                                                   dividido con efectivo)
 *
 * La idempotencia funciona igual que en StockPedidoService::yaDescontado():
 * si el pedido ya tiene factura vigente, se devuelve esa y no se consume
 * un numero nuevo. Asi un pedido contra entrega que ademas tuvo un pago
 * con comprobante no termina con dos facturas.
 *
 * El envio del comprobante al cliente (correo / WhatsApp) NO va aca: un
 * correo que falla no puede revertir una factura ya numerada, porque el
 * numero fiscal se consumio. Eso va en un job, despachado despues del
 * commit (pasos 5 y 6 de la instruccion).
 */
class VentaService
{
    public function __construct(private NumeracionVentaService $numeracion) {}

    // ---------- Emision ----------

    /**
     * Emite la factura del pedido. Idempotente.
     *
     * DEBE invocarse dentro de la transaccion del caller: el lock del
     * timbrado tiene que vivir hasta el commit.
     */
    public function emitirDesdePedido(Pedido $pedido, ?Usuario $usuario = null): VentaCab
    {
        $existente = VentaCab::where('ven_pedid', $pedido->ped_id)
            ->where('ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->first();

        if ($existente) {
            return $existente;   // este pedido ya fue facturado
        }

        $usuario ??= auth()->user();

        if (!$usuario) {
            throw new EmisionFacturaException(
                'No se pudo determinar el usuario emisor de la factura.'
            );
        }

        $pedido->loadMissing([
            'detalles.producto.tipoIva',
            'detalles.producto.unidadMedida',
            'facturacion',
            'entrega',
        ]);

        $sucursal = $this->resolverSucursal($pedido);
        ['timbrado' => $timbrado, 'numero' => $numero] = $this->numeracion->tomarNumero($sucursal->suc_id);
        $caja = $this->resolverCaja($sucursal, $timbrado->tim_puntexp);

        // Emisor = la empresa duena del talonario usado, no la de la
        // sucursal: es la que figura ante la SET en ese timbrado.
        $empresa = $timbrado->empresa;

        if (!$empresa) {
            throw new EmisionFacturaException(
                "El timbrado {$timbrado->tim_numero} no tiene una empresa asociada."
            );
        }

        $lineas   = $this->construirLineas($pedido);
        $totales  = $this->calcularTotales($lineas);

        $this->verificarCoherencia($pedido, $totales['total']);

        $venta = VentaCab::create([
            'ven_timid'   => $timbrado->tim_id,
            'ven_cajid'   => $caja->caj_id,
            'ven_cliid'   => $pedido->ped_cliid,
            'ven_usuid'   => $usuario->usu_id,
            'ven_sucid'   => $sucursal->suc_id,
            'ven_pedid'   => $pedido->ped_id,

            // Snapshot del talonario: si manana alguien corrige el
            // timbrado, la factura emitida sigue diciendo lo que decia.
            'ven_timbrado'    => $timbrado->tim_numero,
            'ven_timb_inicio' => $timbrado->tim_fechin->toDateString(),
            'ven_timb_venc'   => $timbrado->tim_fechfn->toDateString(),
            'ven_emp_ruc'     => $empresa->emp_ruc,
            'ven_emp_razon'   => $empresa->emp_razonsocial,
            'ven_establec'  => $timbrado->tim_establec,
            'ven_puntoexp'  => $timbrado->tim_puntexp,
            'ven_numero'    => $numero,
            'ven_fchaem'    => now()->toDateString(),

            ...$this->datosAdquirente($pedido),

            // E-commerce al contado. La venta a credito de la casa tiene
            // su propio flujo (solicitud_credito) y llenara ven_condicion
            // = 2 con sus cuotas cuando se implemente.
            'ven_condicion'   => VentaCab::CONDICION_CONTADO,
            'ven_cant_cuotas' => null,

            'ven_exenta'   => $totales['exenta'],
            'ven_grav5'    => $totales['grav5'],
            'ven_grav10'   => $totales['grav10'],
            'ven_subtotal' => $totales['subtotal'],
            'ven_total'    => $totales['total'],
            'ven_iva5'     => $totales['iva5'],
            'ven_iva10'    => $totales['iva10'],
            'ven_totiva'   => $totales['totiva'],

            'ven_estado' => VentaCab::ESTADO_EMITIDA,
            'ven_obs'    => "Pedido {$pedido->ped_numero}",
        ]);

        foreach ($lineas as $linea) {
            VentaDetalle::create(['vnd_venid' => $venta->ven_id] + $linea);
        }

        return $venta->load('detalle');
    }

    // ---------- Anulacion ----------

    /**
     * Un documento fiscal no se borra: se anula. La fila queda, con su
     * numero consumido, porque el talonario tiene que poder explicar que
     * paso con cada correlativo.
     *
     * NO devuelve stock. El stock lo movio el pedido (kardex), no la
     * factura: revertirlo exige una devolucion explicita y, si hubo
     * entrega, una nota de credito.
     */
    public function anular(VentaCab $venta, Usuario $usuario, ?string $motivo = null): VentaCab
    {
        if ($venta->anulada()) {
            throw ValidationException::withMessages([
                'factura' => 'Esta factura ya está anulada.',
            ]);
        }

        return DB::transaction(function () use ($venta, $usuario, $motivo) {
            $venta->update([
                'ven_estado'  => VentaCab::ESTADO_ANULADA,
                'ven_anulado' => now(),
                'ven_anusid'  => $usuario->usu_id,
                'ven_motanu'  => $motivo,
            ]);

            return $venta->refresh();
        });
    }

    // ---------- Armado del documento ----------

    /**
     * Una linea por producto del pedido, mas la linea del costo de envio
     * si lo hubo.
     *
     * El envio se factura porque forma parte de ped_total: omitirlo
     * dejaria una factura por menos de lo que el cliente pago. Es la
     * unica linea con vnd_prdid NULL.
     *
     * @return array<int, array<string, mixed>>
     */
    private function construirLineas(Pedido $pedido): array
    {
        $lineas = [];
        $item   = 0;

        foreach ($pedido->detalles as $detalle) {
            $producto = $detalle->producto;

            // El precio del pedido ya viene con IVA incluido, igual que
            // en toda la tienda (Producto::ivaIncluidoEn()).
            $valorVenta = round((float) $detalle->dpd_total, 2);
            $tasa       = (float) ($producto?->tipoIva?->tiv_tasa ?? 10);

            $lineas[] = [
                'vnd_prdid'       => $detalle->dpd_prdid,
                'vnd_nroitem'     => ++$item,
                'vnd_codigo'      => $producto?->prd_codinv ?? (string) $detalle->dpd_prdid,
                'vnd_descripcion' => mb_substr($detalle->dpd_descri, 0, 150),
                'vnd_umedida'     => $producto?->unidadMedida?->umd_codigo ?? 'UN',
                'vnd_cantidad'    => (float) $detalle->dpd_cantid,
                'vnd_precio'      => (float) $detalle->dpd_precio,
                'vnd_descuento'   => 0,
                'vnd_tasa'        => $tasa,
                'vnd_valorventa'  => $valorVenta,
                'vnd_iva'         => $this->ivaPorDentro($valorVenta, $tasa),
            ];
        }

        $envio = round((float) $pedido->ped_cosenv, 2);

        if ($envio > 0) {
            $lineas[] = [
                'vnd_prdid'       => null,
                'vnd_nroitem'     => ++$item,
                'vnd_codigo'      => 'ENVIO',
                'vnd_descripcion' => 'Servicio de entrega a domicilio',
                'vnd_umedida'     => 'UN',
                'vnd_cantidad'    => 1,
                'vnd_precio'      => $envio,
                'vnd_descuento'   => 0,
                'vnd_tasa'        => 10,
                'vnd_valorventa'  => $envio,
                'vnd_iva'         => $this->ivaPorDentro($envio, 10),
            ];
        }

        if (empty($lineas)) {
            throw new EmisionFacturaException('El pedido no tiene líneas que facturar.');
        }

        return $lineas;
    }

    /**
     * IVA por dentro: el precio YA lo contiene. Misma formula que
     * Producto::ivaIncluidoEn(), para que la factura y el resumen del
     * checkout no den numeros distintos.
     */
    private function ivaPorDentro(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa), 2) : 0.0;
    }

    /**
     * Suma vertical del detalle. Los CHECK de venta_cab exigen que
     * subtotal = exenta + grav5 + grav10 y que totiva = iva5 + iva10, asi
     * que todo se calcula desde las lineas: nunca desde el pedido.
     *
     * @param array<int, array<string, mixed>> $lineas
     * @return array<string, float>
     */
    private function calcularTotales(array $lineas): array
    {
        $acum = ['exenta' => 0.0, 'grav5' => 0.0, 'grav10' => 0.0, 'iva5' => 0.0, 'iva10' => 0.0];

        foreach ($lineas as $linea) {
            $tasa  = (float) $linea['vnd_tasa'];
            $valor = (float) $linea['vnd_valorventa'];
            $iva   = (float) $linea['vnd_iva'];

            if ($tasa == 10) {
                $acum['grav10'] += $valor;
                $acum['iva10']  += $iva;
            } elseif ($tasa == 5) {
                $acum['grav5'] += $valor;
                $acum['iva5']  += $iva;
            } else {
                $acum['exenta'] += $valor;
            }
        }

        $subtotal = round($acum['exenta'] + $acum['grav5'] + $acum['grav10'], 2);

        return [
            'exenta'   => round($acum['exenta'], 2),
            'grav5'    => round($acum['grav5'], 2),
            'grav10'   => round($acum['grav10'], 2),
            'subtotal' => $subtotal,
            'total'    => $subtotal,
            'iva5'     => round($acum['iva5'], 2),
            'iva10'    => round($acum['iva10'], 2),
            'totiva'   => round($acum['iva5'] + $acum['iva10'], 2),
        ];
    }

    /**
     * La factura tiene que valer lo mismo que el pedido. Si no coincide
     * hay un error de calculo en algun lado, pero NO se aborta la
     * emision: el cliente ya pago y dejarlo sin comprobante es peor.
     * Queda el rastro en el log para revisarlo.
     */
    private function verificarCoherencia(Pedido $pedido, float $total): void
    {
        $esperado = (float) $pedido->ped_total;

        if (abs($esperado - $total) > 1) {
            Log::warning('Factura de venta con total distinto al del pedido', [
                'pedido'          => $pedido->ped_numero,
                'total_pedido'    => $esperado,
                'total_factura'   => $total,
            ]);
        }
    }

    /**
     * Snapshot del adquirente. Se copia de pedido_facturacion, que es lo
     * que el cliente declaro en el Paso 2 del checkout y puede diferir de
     * los datos de su persona (factura a nombre de la empresa, por
     * ejemplo).
     *
     * @return array<string, ?string>
     */
    private function datosAdquirente(Pedido $pedido): array
    {
        $fac = $pedido->facturacion;

        if (!$fac) {
            throw new EmisionFacturaException(
                'El pedido no tiene datos de facturación cargados: no se puede emitir la factura.'
            );
        }

        $esRuc = $fac->pfa_tipodoc === 'RUC';

        return [
            'ven_razonsocial' => mb_substr($fac->pfa_raznom, 0, 150),
            'ven_ruc'         => $esRuc ? $fac->pfa_nrodoc : null,
            'ven_ci'          => $esRuc ? null : $fac->pfa_nrodoc,
            'ven_direccion'   => $this->direccion($pedido),
            'ven_telefono'    => $fac->pfa_telefono,
            'ven_email'       => $fac->pfa_correo,
        ];
    }

    private function direccion(Pedido $pedido): ?string
    {
        $entrega = $pedido->entrega;

        if (!$entrega) {
            return null;
        }

        $partes = array_filter([
            $entrega->pen_calle,
            $entrega->pen_nrocasa,
            $entrega->pen_barrio,
            $entrega->pen_ciudad,
        ]);

        return $partes ? mb_substr(implode(' ', $partes), 0, 150) : null;
    }

    // ---------- Resolucion de configuracion fiscal ----------

    /**
     * Sucursal emisora. En retiro en sucursal viene en el pedido; en
     * envio a domicilio ped_sucid es NULL y se usa la casa matriz, que es
     * la que tiene el timbrado de la tienda web.
     */
    private function resolverSucursal(Pedido $pedido): Sucursal
    {
        $sucursal = $pedido->ped_sucid
            ? Sucursal::find($pedido->ped_sucid)
            : Sucursal::where('suc_princi', 1)->where('suc_estado', 1)->first();

        if (!$sucursal) {
            throw new EmisionFacturaException(
                'No se pudo determinar la sucursal emisora. Marcá una sucursal como principal.'
            );
        }

        return $sucursal;
    }

    /**
     * La caja es el punto de expedicion, y tiene que ser el MISMO que el
     * del talonario: si el timbrado numera 001-002-xxxxxxx, la factura no
     * puede declararse emitida desde la caja 001.
     */
    private function resolverCaja(Sucursal $sucursal, string $puntoExpedicion): Caja
    {
        $caja = Caja::where('caj_sucid', $sucursal->suc_id)
            ->where('caj_puntexp', $puntoExpedicion)
            ->where('caj_estado', 1)
            ->first();

        if (!$caja) {
            throw new EmisionFacturaException(
                "No hay una caja activa para el punto de expedición {$puntoExpedicion} "
                . "de la sucursal {$sucursal->suc_nombre}. Ejecutá el CajaSeeder o cargala a mano."
            );
        }

        return $caja;
    }
}
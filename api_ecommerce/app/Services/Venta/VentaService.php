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
use App\Models\Compras\Stock;
use App\Models\Configuracion\Deposito;
use App\Models\Pedido\EstadoPedido;
use App\Jobs\DeclararFacturaElectronica;
use App\Models\Venta\VentaFe;

use App\Models\Venta\CtaCobrar;
/**
 * Emision del comprobante fiscal a partir de un pedido.
 *
 * Unico punto de emision: generarDesdePedido(), la accion [GENERAR
 * FACTURA] del panel. Ni el cobro ni la entrega emiten nada; si lo
 * hicieran, el mismo pedido podria facturarse por dos caminos distintos y
 * con reglas distintas.
 *
 * emitirDesdePedido() sigue siendo idempotente, igual que
 * StockPedidoService::yaDescontado(): si el pedido ya tiene factura
 * vigente devuelve esa y no consume un numero nuevo. Es la red de
 * seguridad ante un doble clic o un reintento.)
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

        /**
     * Accion [GENERAR FACTURA] del panel. Es el unico camino por el que el
     * administrador emite una factura de pedido.
     *
     * A diferencia de emitirDesdePedido(), que es idempotente y silencioso,
     * aca una factura ya existente es un ERROR: el admin apreto el boton
     * creyendo que no habia documento, y devolverle la factura vieja sin
     * decir nada esconde el malentendido.
     */
    public function generarDesdePedido(Pedido $pedido, Usuario $usuario): VentaCab
    {
        return DB::transaction(function () use ($pedido, $usuario) {
                        $pedido->loadMissing('estadoPedido', 'pagos.metodoPago', 'pagos.comprobante', 'pagos.cheque');

            if ($pedido->estadoPedido?->est_codigo === EstadoPedido::CANCELADO) {
                throw ValidationException::withMessages([
                    'factura' => 'El pedido está cancelado: no se le puede generar factura.',
                ]);
            }

            $existente = VentaCab::where('ven_pedid', $pedido->ped_id)
                ->vigente()
                ->lockForUpdate()
                ->first();

            if ($existente) {
                throw ValidationException::withMessages([
                    'factura' => "Este pedido ya tiene la factura {$existente->ven_nrodoc}.",
                ]);
            }

            $this->validarHabilitacion($pedido);

            return $this->emitirDesdePedido($pedido, $usuario);
        });
    }

    /**
     * Cuando se puede facturar un pedido. Dos condiciones, y la segunda
     * solo aplica si hay dinero en efectivo de por medio:
     *
     *   1. Ningun comprobante sin validar. La factura se declara a la DNIT
     *      con TODOS los medios de pago que eligio el cliente, asi que no
     *      puede emitirse mientras quede uno sin mirar.
     *
     *   2. Si algun pago es contra entrega (efectivo), el pedido tiene que
     *      estar LISTO. Esa plata entra al momento de la entrega, y la
     *      factura se imprime y se entrega junto con la mercaderia: antes
     *      de LISTO no hay nada que entregar.
     *
     * Un pedido mixto (parte transferencia, parte efectivo) cae en las dos
     * y por lo tanto espera a LISTO, aunque su comprobante ya este
     * validado hace dias.
     */
    private function validarHabilitacion(Pedido $pedido): void
    {
        // Un pedido de crédito no tiene pagos: las reglas de abajo
        // (comprobantes, cheques, efectivo) no le aplican y se cumplirían
        // solas desde el primer segundo.
        if ($pedido->esDeCredito()) {
            $this->validarHabilitacionCredito($pedido);

            return;
        }

        $sinValidar = $pedido->pagos
            ->filter(fn ($pago) => (bool) $pago->metodoPago?->mpa_requcom)
            ->filter(fn ($pago) => !$pago->comprobante?->pco_verifi)
            ->count();

        if ($sinValidar > 0) {
            throw ValidationException::withMessages([
                'factura' => $sinValidar === 1
                    ? 'Queda un comprobante sin validar. Validalo antes de generar la factura.'
                    : "Quedan {$sinValidar} comprobantes sin validar. Validalos antes de generar la factura.",
            ]);
        }

        // Antes esto lo cubría, de rebote, la exigencia de LISTO. Ahora que
        // el cheque ya no la dispara, se valida de forma explícita.
        $chequesSinRecibir = $pedido->pagos
            ->filter(fn ($pago) => $pago->cheque && !$pago->cheque->fueRecibido())
            ->count();

        if ($chequesSinRecibir > 0) {
            throw ValidationException::withMessages([
                'factura' => 'Falta confirmar la recepción del cheque en la sucursal antes de generar la factura.',
            ]);
        }

                if ($chequesSinRecibir > 0) {
            throw ValidationException::withMessages([
                'factura' => 'Falta confirmar la recepción del cheque en la sucursal antes de generar la factura.',
            ]);
        }

        // El cheque recibido tiene que estar completo: la DNIT exige su
        // número y su banco dentro del comprobante. Sin esto la factura se
        // emite igual y recién falla al declararla, cuando el número fiscal
        // ya se consumió y no hay forma de corregirlo.
        $chequesIncompletos = $pedido->pagos
            ->filter(fn ($pago) => $pago->cheque && !$pago->cheque->datosCompletos())
            ->count();

        if ($chequesIncompletos > 0) {
            throw ValidationException::withMessages([
                'factura' => 'Faltan datos del cheque (banco, número, titular o fecha de cobro). '
                    . 'Completalos en la gestión del cheque antes de generar la factura.',
            ]);
        }

        $hayEfectivo = $pedido->pagos
            ->contains(fn ($pago) => !$pago->metodoPago?->requiereVerificacion());

        if ($hayEfectivo && $pedido->estadoPedido?->est_codigo !== EstadoPedido::LISTO) {
            throw ValidationException::withMessages([
                'factura' => 'Este pedido tiene un pago en efectivo: la factura se genera cuando el pedido está LISTO para entregar.',
            ]);
        }
    }

    /**
     * Habilitación de la factura de un pedido a crédito: el pedido tiene que
     * estar LISTO para entrega/retiro. La factura se entrega en mano junto
     * con el producto y la documentación firmada, así que antes de LISTO no
     * hay nada que entregar.
    */
    private function validarHabilitacionCredito(Pedido $pedido): void
    {
        if ($pedido->estadoPedido?->est_codigo !== EstadoPedido::LISTO) {
            throw ValidationException::withMessages([
                'factura' => 'La factura a crédito se genera cuando el pedido está LISTO para entrega/retiro.',
            ]);
        }

    }

    public function emitirDesdePedido(Pedido $pedido, ?Usuario $usuario = null): VentaCab
    {
        // lockForUpdate: dos confirmaciones simultaneas no pueden numerar
        // dos veces el mismo pedido.
        $existente = VentaCab::where('ven_pedid', $pedido->ped_id)
            ->vigente()
            ->lockForUpdate()
            ->first();

        // Idempotente: si el pedido ya tiene factura vigente se devuelve
        // esa y no se consume otro numero del talonario.
        if ($existente) {
            return $existente->load('detalle');
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

        // Emisor = la empresa duena del talonario usado (la que figura ante la SET).
        $empresa = $timbrado->empresa;

        if (!$empresa) {
            throw new EmisionFacturaException(
                "El timbrado {$timbrado->tim_numero} no tiene una empresa asociada."
            );
        }

        [$lineas, $totales] = $this->armarDocumento($pedido, $sucursal);

        $venta = VentaCab::create($this->datosComunes($pedido, $totales) + [
            'ven_timid'       => $timbrado->tim_id,
            'ven_cajid'       => $caja->caj_id,
            'ven_usuid'       => $usuario->usu_id,
            'ven_sucid'       => $sucursal->suc_id,
            'ven_timbrado'    => $timbrado->tim_numero,
            'ven_timb_inicio' => $timbrado->tim_fechin->toDateString(),
            'ven_timb_venc'   => $timbrado->tim_fechfn->toDateString(),
            'ven_emp_ruc'     => $empresa->emp_ruc,
            'ven_emp_razon'   => $empresa->emp_razonsocial,
            'ven_establec'    => $timbrado->tim_establec,
            'ven_puntoexp'    => $timbrado->tim_puntexp,
            'ven_numero'      => $numero,
            'ven_fchaem'      => now()->toDateString(),
            'ven_estado'      => VentaCab::ESTADO_EMITIDA,
        ]);

        $this->guardarLineas($venta, $lineas);

        // El cronograma a cobrar nace con la factura: antes de este punto
        // solo existe la OFERTA aceptada por el cliente.
        $this->generarCuotas($venta, $pedido);

        $this->programarDeclaracion($venta);

        return $venta->load('detalle');
    }

    /**
     * Copia el cronograma aceptado de la solicitud a cta_cobrar.
     *
     * Es una COPIA, no una lectura por referencia: desde que la factura se
     * emite, el cronograma a cobrar es independiente de la solicitud.
     * Mismo criterio de snapshot que ya se usa con el precio unitario.
     *
     * No hay recalculo: los importes y vencimientos son exactamente los
     * que el cliente acepto. Recalcular aca abriria la puerta a que la
     * deuda no coincida con lo que el cliente firmo.
     */
    private function generarCuotas(VentaCab $venta, Pedido $pedido): void
    {
        if (!$pedido->esDeCredito()) {
            return;
        }

        $solicitud = $pedido->solicitudCredito;

        if (!$solicitud) {
            throw new EmisionFacturaException(
                "El pedido {$pedido->ped_numero} es a crédito pero no tiene solicitud asociada."
            );
        }

        $cronograma = $solicitud->cuotas()->get();

        if ($cronograma->isEmpty()) {
            throw new EmisionFacturaException(
                "La solicitud {$solicitud->scr_numero} no tiene cronograma: no se puede facturar a crédito."
            );
        }

        // La suma de las cuotas tiene que dar el total facturado. Si no da,
        // la factura y la deuda dirian cosas distintas: mejor no emitir.
        $sumaCuotas = round((float) $cronograma->sum('scc_import'), 2);
        $totalVenta = round((float) $venta->ven_total, 2);

        if (abs($sumaCuotas - $totalVenta) > 0.01) {
            throw new EmisionFacturaException(
                "El cronograma suma {$sumaCuotas} y la factura {$totalVenta}: no coinciden."
            );
        }

        // La emision de la cuota es la fecha de la factura: antes de este
        // momento la deuda no existia.
        $emision = $venta->ven_fchaem;

        foreach ($cronograma as $cuota) {
            // ck_cco_vencim exige vencimiento >= emision. Una cuota cuyo
            // vencimiento ya paso (credito aprobado hace tiempo, entrega
            // demorada) rompe esa regla: se corre al dia de la emision.
            $vencimiento = $cuota->scc_fecven->lt($emision) ? $emision : $cuota->scc_fecven;

            CtaCobrar::create([
                'cco_venid'  => $venta->ven_id,
                'cco_nrocuo' => $cuota->scc_numero,
                'cco_femisi' => $emision,
                'cco_vencim' => $vencimiento,
                'cco_monto'  => $cuota->scc_import,
                'cco_saldo'  => $cuota->scc_import,   // sin parciales: saldo = monto
                'cco_compos' => $cuota->scc_compos,
                'cco_estado' => CtaCobrar::ESTADO_PENDIENTE,
            ]);
        }
    }

    /**
     * Deja la declaracion electronica programada. El registro venta_fe se crea
     * DENTRO de la transaccion (si el cobro se revierte, desaparece) y el job se
     * despacha DESPUES del commit: la llamada a la API nunca bloquea el cobro.
     */
    private function programarDeclaracion(VentaCab $venta): void
    {
        if (!config('facturacion.activa')) {
            return;
        }

        VentaFe::firstOrCreate(
            ['vfe_venid' => $venta->ven_id],
            ['vfe_estado' => VentaFe::ESTADO_PENDIENTE]
        );

        DeclararFacturaElectronica::dispatch($venta->ven_id)->afterCommit();
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

        $this->verificarSinNotasCredito($venta);
        $this->verificarSinDeclaracionElectronica($venta);

        

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


    /** @return array{0: array<int, array<string, mixed>>, 1: array<string, float>} */
        private function armarDocumento(Pedido $pedido, Sucursal $sucursal): array
    {
        $lineas  = $this->construirLineas($pedido, $sucursal);
        $totales = $this->calcularTotales($lineas);

        $this->verificarCoherencia($pedido, $totales['total']);

        return [$lineas, $totales];
    }

    /** Datos que NO dependen del timbrado: iguales en el borrador y en la emisión directa. */
    private function datosComunes(Pedido $pedido, array $totales): array
    {
        return [
            'ven_cliid' => $pedido->ped_cliid,
            'ven_pedid' => $pedido->ped_id,

            ...$this->datosAdquirente($pedido),

            // E-commerce al contado (el crédito tiene su propio flujo).
            // La condicion la define el origen del pedido: si nacio de una
            // solicitud de credito, la factura es a credito y lleva el plazo
            // pactado. El CHECK ck_ven_condicion exige que las dos columnas
            // sean coherentes entre si.
            'ven_condicion'   => $pedido->esDeCredito()
                ? VentaCab::CONDICION_CREDITO
                : VentaCab::CONDICION_CONTADO,
            'ven_cant_cuotas' => $pedido->esDeCredito()
                ? $pedido->solicitudCredito?->cuotas()->count()
                : null,

            'ven_exenta'   => $totales['exenta'],
            'ven_grav5'    => $totales['grav5'],
            'ven_grav10'   => $totales['grav10'],
            'ven_subtotal' => $totales['subtotal'],
            'ven_total'    => $totales['total'],
            'ven_iva5'     => $totales['iva5'],
            'ven_iva10'    => $totales['iva10'],
            'ven_totiva'   => $totales['totiva'],

            'ven_obs' => "Pedido {$pedido->ped_numero}",
        ];
    }

    private function guardarLineas(VentaCab $venta, array $lineas): void
    {
        foreach ($lineas as $linea) {
            VentaDetalle::create(['vnd_venid' => $venta->ven_id] + $linea);
        }
    }

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
    private function construirLineas(Pedido $pedido, Sucursal $sucursal): array
    {
        $lineas = [];
        $item   = 0;

         // Mismo deposito del que StockPedidoService descuenta la mercaderia:
        // el principal de la sucursal emisora. Las salidas no alteran el CPP,
        // asi que este costo coincide con el mst_cosuni del kardex.
        $depositoId = Deposito::where('dpo_sucid', $sucursal->suc_id)
            ->where('dpo_princi', 1)
            ->value('dpo_id');

        foreach ($pedido->detalles as $detalle) {
                        // El precio del pedido ya viene con IVA incluido, igual que
            // en toda la tienda (Producto::ivaIncluidoEn()).
            $valorVenta = round((float) $detalle->dpd_total, 2);
            $cantidad   = (float) $detalle->dpd_cantid;
            $tasa       = (float) ($producto?->tipoIva?->tiv_tasa ?? 10);

            // El unitario se deriva del valor de la linea, no se copia de
            // dpd_precio: asi cantidad x precio = valorventa siempre, que
            // es lo que la DNIT recalcula. Si el pedido guardo el precio
            // de contado y el total financiado, la factura igual sale bien.
            $precioUnit = $cantidad > 0 ? round($valorVenta / $cantidad, 4) : 0.0;

            $lineas[] = [
                'vnd_prdid'       => $detalle->dpd_prdid,
                'vnd_nroitem'     => ++$item,
                'vnd_codigo'      => $producto?->prd_codinv ?? (string) $detalle->dpd_prdid,
                'vnd_descripcion' => mb_substr($detalle->dpd_descri, 0, 150),
                'vnd_umedida'     => $producto?->unidadMedida?->umd_codigo ?? 'UN',
                'vnd_cantidad'    => $cantidad,
                'vnd_precio'      => $precioUnit,
                'vnd_descuento'   => 0,
                'vnd_tasa'        => $tasa,
                'vnd_valorventa'  => $valorVenta,
                'vnd_iva'         => $this->ivaPorDentro($valorVenta, $tasa),
                'vnd_cosuni'      => $this->costoUnitario($detalle->dpd_prdid, $depositoId),
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
               'vnd_cosuni'      => null,
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
     * CPP sin IVA del producto en el deposito de despacho, congelado en la
     * factura para que el margen historico no cambie con compras futuras.
     */
    private function costoUnitario(?int $productoId, ?int $depositoId): ?float
    {
        if (!$productoId || !$depositoId) {
            return null;
        }

        $costo = Stock::where('sto_prdid', $productoId)
            ->where('sto_depid', $depositoId)
            ->value('sto_cosprm');

        return $costo !== null ? round((float) $costo, 4) : 0.0;
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

    /**
    * Una factura declarada (o en proceso de declararse) ante la DNIT no se anula
    * internamente: el sistema quedaria en desacuerdo con la DNIT. Se corrige con
    * una nota de credito.
    */
    private function verificarSinDeclaracionElectronica(VentaCab $venta): void
    {
        if ($venta->fe?->bloqueaAnulacion()) {
            throw ValidationException::withMessages([
                'factura' => 'Esta factura ya fue declarada (o está en proceso de declararse) ante la DNIT. '
                           . 'No se anula desde acá: se corrige con una nota de crédito.',
            ]);
        }
    }

    /**
    * Una factura con notas de crédito vigentes no se anula: la NC ya
    * corrigió parte de su importe, y anular la factura dejaría una NC
    * apuntando a un documento que dejó de valer. Primero se anulan las NC.
    */
    private function verificarSinNotasCredito(VentaCab $venta): void
    {
        $cantidad = $venta->notasCreditoVigentes()->count();

        if ($cantidad > 0) {
            throw ValidationException::withMessages([
                'factura' => "Esta factura tiene {$cantidad} nota(s) de crédito vigente(s). "
                           . 'Anulá primero las notas de crédito y después la factura.',
            ]);
        }
    }
}
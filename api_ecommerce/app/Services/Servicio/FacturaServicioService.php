<?php

namespace App\Services\Servicio;

use App\Exceptions\EmisionFacturaException;
use App\Jobs\DeclararFacturaElectronica;
use App\Models\Compras\Stock;
use App\Models\Configuracion\Sucursal;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\OrdenServicio;
use App\Models\Servicio\PresupuestoDetalle;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\Trabajo;
use App\Models\Usuario;
use App\Models\Venta\Caja;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaDetalle;
use App\Models\Venta\VentaFe;
use App\Services\Venta\NumeracionVentaService;

/**
 * Emisión de la factura (venta_cab con ven_sseid) de una solicitud de
 * servicio técnico. Equivalente a VentaService::emitirDesdePedido(), que
 * NO se toca: se reutilizan NumeracionVentaService, VentaCab/VentaDetalle,
 * venta_fe y el job DeclararFacturaElectronica tal como están.
 *
 * Cuándo: cuando el cobro queda CONFIRMADO (lo dispara
 * EstadoPagoServicioService::recalcular(), Paso 5). Factura al CONTADO: los
 * medios de pago (uno o varios, p. ej. mitad transferencia y mitad
 * efectivo) los arma el builder del Paso 4 a partir de servicio_pago.
 *
 * Líneas (todas con IVA incluido, igual que los precios del sistema):
 *   - Mano de obra de TODOS los presupuestos aprobados (v1 + complementarios).
 *   - Repuestos realmente USADOS (orden_servicio_repuesto.osr_cantid > 0).
 *   - No reparable: una línea "Revisión técnica" por sse_subtot.
 *   - Retiro a domicilio (sse_cosret) y envío (sse_cosenv), si los hay.
 * Es la misma suma que ServicioTotalesService::consolidar() dejó en
 * sse_total; si no coincide, NO se emite (ver verificarTotal()).
 *
 * DEBE invocarse dentro de la transacción del cobro: el lock del timbrado
 * (NumeracionVentaService) vive hasta el commit, y si la emisión falla el
 * cobro se revierte (mismo criterio que el pedido, ver EmisionFacturaException).
 */
class FacturaServicioService
{
    /** Diferencia aceptada (Gs.) entre la suma de las líneas y sse_total. */
    private const TOLERANCIA = 1.0;

    private const TASA_SERVICIO = 10.0;

    public function __construct(
        private NumeracionVentaService $numeracion,
        private FacturacionServicioService $datosFiscales,
    ) {}

    // ---------- Emisión ----------

    /**
     * Emite la factura del servicio. Idempotente: si ya tiene una vigente,
     * la devuelve sin consumir otro número.
     *
     * @return VentaCab|null null si el servicio no se factura (garantía o total 0)
     *
     * @throws EmisionFacturaException
     */
    public function emitir(SolicitudServicio $s, Usuario $usuario): ?VentaCab
    {
        $existente = $s->factura()->first();
        if ($existente) {
            return $existente;
        }

        // Regla 8: la garantía no genera cobro ni factura. Total 0, tampoco.
        if ($s->sse_garant || (float) $s->sse_total <= 0) {
            return null;
        }

        // load() y no loadMissing(): recalcular() corre en la misma transacción
        // que cierra la OT (finalizar / no reparable) y la relación podría estar
        // cargada desde antes, con ose_result o cantidades viejas.
        $s->load([
            'ordenServicio.repuestos.producto.tipoIva',
            'ordenServicio.repuestos.producto.unidadMedida',
            'facturacion',
            'cliente.persona',
        ]);

        // Antes de tomar el número: un error acá no debe gastar un correlativo
        // (igual se revertiría con la transacción, pero así el lock dura menos).
        $sucursal = $this->resolverSucursal($s);
        $lineas   = $this->construirLineas($s, $sucursal);
        $totales  = $this->calcularTotales($lineas);
        $this->verificarTotal($s, $totales['total']);
        $adquirente = $this->datosFiscales->adquirente($s);

        ['timbrado' => $timbrado, 'numero' => $numero] = $this->numeracion->tomarNumero($sucursal->suc_id);

        $caja    = $this->resolverCaja($sucursal, $timbrado->tim_puntexp);
        $empresa = $timbrado->empresa;

        if (!$empresa) {
            throw new EmisionFacturaException(
                "El timbrado {$timbrado->tim_numero} no tiene una empresa asociada."
            );
        }

        $venta = new VentaCab([
            'ven_timid' => $timbrado->tim_id,
            'ven_cajid' => $caja->caj_id,
            'ven_cliid' => $s->sse_cliid,
            'ven_usuid' => $usuario->usu_id,
            'ven_sucid' => $sucursal->suc_id,
            'ven_pedid' => null,

            // Snapshot del talonario y del emisor (mismo criterio que el pedido).
            'ven_timbrado'    => $timbrado->tim_numero,
            'ven_timb_inicio' => $timbrado->tim_fechin->toDateString(),
            'ven_timb_venc'   => $timbrado->tim_fechfn->toDateString(),
            'ven_emp_ruc'     => $empresa->emp_ruc,
            'ven_emp_razon'   => $empresa->emp_razonsocial,

            'ven_establec' => $timbrado->tim_establec,
            'ven_puntoexp' => $timbrado->tim_puntexp,
            'ven_numero'   => $numero,
            'ven_fchaem'   => now()->toDateString(),

            ...$adquirente,

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
            'ven_obs'    => "Servicio técnico {$s->sse_numero}",
        ]);

        // ven_sseid no está en $fillable de VentaCab (que no se modifica):
        // se asigna directo, sin pasar por la asignación masiva.
        $venta->ven_sseid = $s->sse_id;
        $venta->save();

        foreach ($lineas as $linea) {
            VentaDetalle::create(['vnd_venid' => $venta->ven_id] + $linea);
        }

        $this->programarDeclaracion($venta);

        return $venta->load('detalle');
    }

    /**
     * Igual que VentaService::programarDeclaracion() (privado, por eso se
     * repite): venta_fe se crea DENTRO de la transacción y el job se despacha
     * DESPUÉS del commit. La llamada a la API nunca bloquea el cobro.
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

    // ---------- Líneas ----------

    /** @return array<int, array<string, mixed>> */
    private function construirLineas(SolicitudServicio $s, Sucursal $sucursal): array
    {
        $orden = $s->ordenServicio;
        $item  = 0;

        $lineas = $orden?->ose_result === OrdenServicio::NO_REPARABLE
            ? $this->lineaRevision($s, $item)
            : [...$this->lineasManoObra($orden, $item), ...$this->lineasRepuestos($orden, $item)];

        $retiro = round((float) $s->sse_cosret, 2);
        if ($retiro > 0) {
            $lineas[] = $this->lineaServicio(++$item, 'RETIRO', 'Retiro del equipo a domicilio', $retiro);
        }

        $envio = round((float) $s->sse_cosenv, 2);
        if ($envio > 0) {
            $lineas[] = $this->lineaServicio(++$item, 'ENVIO', 'Envío del equipo a domicilio', $envio);
        }

        if (empty($lineas)) {
            throw new EmisionFacturaException(
                "La solicitud {$s->sse_numero} no tiene líneas que facturar."
            );
        }

        return $lineas;
    }

    /**
     * Mano de obra de todas las versiones APROBADAS. La v2+ solo contiene lo
     * adicional, así que se suman todas sin duplicar nada.
     */
    private function lineasManoObra(?OrdenServicio $orden, int &$item): array
    {
        if (!$orden) {
            return [];
        }

        $aprobados = $orden->presupuestos()
            ->whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::APROBADO))
            ->pluck('pse_id');

        $detalles = PresupuestoDetalle::with('trabajo.tipoIva')
            ->whereIn('psd_pseid', $aprobados)
            ->where('psd_tipo', PresupuestoDetalle::MANO_OBRA)
            ->orderBy('psd_pseid')
            ->orderBy('psd_id')
            ->get();

        $lineas = [];

        foreach ($detalles as $d) {
            $valor = round((float) $d->psd_total, 2);
            if ($valor <= 0) {
                continue;
            }

            $tasa = (float) ($d->trabajo?->tipoIva?->tiv_tasa ?? self::TASA_SERVICIO);

            $lineas[] = [
                'vnd_prdid'       => null,
                'vnd_nroitem'     => ++$item,
                'vnd_codigo'      => $d->trabajo?->trb_codigo ?? 'MO',
                'vnd_descripcion' => mb_substr((string) $d->psd_descri, 0, 150),
                'vnd_umedida'     => 'UN',
                'vnd_cantidad'    => (float) $d->psd_cantid,
                'vnd_precio'      => (float) $d->psd_precio,
                'vnd_descuento'   => 0,
                'vnd_tasa'        => $tasa,
                'vnd_valorventa'  => $valor,
                'vnd_iva'         => $this->ivaPorDentro($valor, $tasa),
                'vnd_cosuni'      => null,
            ];
        }

        return $lineas;
    }

    /** Repuestos realmente usados (Regla 4: puede ser menos que lo aprobado). */
    private function lineasRepuestos(?OrdenServicio $orden, int &$item): array
    {
        if (!$orden) {
            return [];
        }

        $lineas = [];

        foreach ($orden->repuestos as $osr) {
            $cantidad = (float) $osr->osr_cantid;
            if ($cantidad <= 0) {
                continue;
            }

            $producto = $osr->producto;
            $valor    = round($cantidad * (float) $osr->osr_precio, 2);
            $tasa     = (float) ($producto?->tipoIva?->tiv_tasa ?? 10);

            $lineas[] = [
                'vnd_prdid'       => $osr->osr_prdid,
                'vnd_nroitem'     => ++$item,
                'vnd_codigo'      => $producto?->prd_codinv ?? (string) $osr->osr_prdid,
                'vnd_descripcion' => mb_substr((string) ($producto?->prd_descri ?? 'Repuesto'), 0, 150),
                'vnd_umedida'     => $producto?->unidadMedida?->umd_codigo ?? 'UN',
                'vnd_cantidad'    => $cantidad,
                'vnd_precio'      => (float) $osr->osr_precio,
                'vnd_descuento'   => 0,
                'vnd_tasa'        => $tasa,
                'vnd_valorventa'  => $valor,
                'vnd_iva'         => $this->ivaPorDentro($valor, $tasa),
                'vnd_cosuni'      => $this->costoUnitario($osr->osr_prdid, $osr->osr_depid),
            ];
        }

        return $lineas;
    }

    /**
     * No reparable: la revisión no se guarda como línea, quedó solo en
     * sse_subtot (OrdenTrabajoService::cerrarNoReparable). Se reconstruye
     * con ese monto y la tasa del trabajo REVISION.
     */
    private function lineaRevision(SolicitudServicio $s, int &$item): array
    {
        $monto = round((float) $s->sse_subtot, 2);
        if ($monto <= 0) {
            return [];
        }

        $codigo  = config('servicio.trabajo_revision');
        $trabajo = Trabajo::with('tipoIva')->where('trb_codigo', $codigo)->first();
        $tasa    = (float) ($trabajo?->tipoIva?->tiv_tasa ?? self::TASA_SERVICIO);

        return [[
            'vnd_prdid'       => null,
            'vnd_nroitem'     => ++$item,
            'vnd_codigo'      => $codigo,
            'vnd_descripcion' => mb_substr($trabajo?->trb_descri ?: 'Revisión técnica', 0, 150),
            'vnd_umedida'     => 'UN',
            'vnd_cantidad'    => 1,
            'vnd_precio'      => $monto,
            'vnd_descuento'   => 0,
            'vnd_tasa'        => $tasa,
            'vnd_valorventa'  => $monto,
            'vnd_iva'         => $this->ivaPorDentro($monto, $tasa),
            'vnd_cosuni'      => null,
        ]];
    }

    /** Retiro / envío: prestación de servicio gravada al 10 %. */
    private function lineaServicio(int $item, string $codigo, string $descripcion, float $monto): array
    {
        return [
            'vnd_prdid'       => null,
            'vnd_nroitem'     => $item,
            'vnd_codigo'      => $codigo,
            'vnd_descripcion' => $descripcion,
            'vnd_umedida'     => 'UN',
            'vnd_cantidad'    => 1,
            'vnd_precio'      => $monto,
            'vnd_descuento'   => 0,
            'vnd_tasa'        => self::TASA_SERVICIO,
            'vnd_valorventa'  => $monto,
            'vnd_iva'         => $this->ivaPorDentro($monto, self::TASA_SERVICIO),
            'vnd_cosuni'      => null,
        ];
    }

    // ---------- Totales (mismo cálculo que VentaService, que no se toca) ----------

    /** IVA por dentro, a 2 decimales como en venta_detalle del pedido. */
    private function ivaPorDentro(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa), 2) : 0.0;
    }

    /**
     * Suma vertical del detalle: los CHECK de venta_cab exigen
     * subtotal = exenta + grav5 + grav10 y totiva = iva5 + iva10.
     *
     * @return array<string, float>
     */
    private function calcularTotales(array $lineas): array
    {
        $acum = ['exenta' => 0.0, 'grav5' => 0.0, 'grav10' => 0.0, 'iva5' => 0.0, 'iva10' => 0.0];

        foreach ($lineas as $l) {
            $tasa  = (float) $l['vnd_tasa'];
            $valor = (float) $l['vnd_valorventa'];
            $iva   = (float) $l['vnd_iva'];

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

        $exenta   = round($acum['exenta'], 2);
        $grav5    = round($acum['grav5'], 2);
        $grav10   = round($acum['grav10'], 2);
        $iva5     = round($acum['iva5'], 2);
        $iva10    = round($acum['iva10'], 2);
        $subtotal = round($exenta + $grav5 + $grav10, 2);

        return [
            'exenta'   => $exenta,
            'grav5'    => $grav5,
            'grav10'   => $grav10,
            'subtotal' => $subtotal,
            'total'    => $subtotal,
            'iva5'     => $iva5,
            'iva10'    => $iva10,
            'totiva'   => round($iva5 + $iva10, 2),
        ];
    }

    /**
     * A diferencia del pedido (que solo deja un log), acá SÍ se frena: la
     * factura al contado declara pagos que suman sse_total, y si las líneas
     * dieran otro número la API la rechazaría y quedaría trabada. Es mejor
     * que el cobro no se confirme y el mensaje diga qué revisar.
     */
    private function verificarTotal(SolicitudServicio $s, float $total): void
    {
        $esperado = (float) $s->sse_total;

        if (abs($esperado - $total) > self::TOLERANCIA) {
            throw new EmisionFacturaException(sprintf(
                'No se puede emitir la factura de %s: las líneas suman Gs. %s y el total del servicio es Gs. %s. '
                . 'Revisá mano de obra, repuestos usados y costos de retiro/envío.',
                $s->sse_numero,
                number_format($total, 0, ',', '.'),
                number_format($esperado, 0, ',', '.')
            ));
        }
    }

    // ---------- Configuración fiscal (mismo criterio que VentaService) ----------

    /**
     * Sucursal emisora: la de la solicitud (mostrador o retiro en local); si
     * es NULL (web con retiro a domicilio), la casa matriz.
     */
    private function resolverSucursal(SolicitudServicio $s): Sucursal
    {
        $sucursal = $s->sse_sucid
            ? Sucursal::find($s->sse_sucid)
            : Sucursal::where('suc_princi', 1)->where('suc_estado', 1)->first();

        if (!$sucursal) {
            throw new EmisionFacturaException(
                'No se pudo determinar la sucursal emisora. Marcá una sucursal como principal.'
            );
        }

        return $sucursal;
    }

    /** La caja es el punto de expedición y debe coincidir con el del talonario. */
    private function resolverCaja(Sucursal $sucursal, string $puntoExpedicion): Caja
    {
        $caja = Caja::where('caj_sucid', $sucursal->suc_id)
            ->where('caj_puntexp', $puntoExpedicion)
            ->where('caj_estado', 1)
            ->first();

        if (!$caja) {
            throw new EmisionFacturaException(
                "No hay una caja activa para el punto de expedición {$puntoExpedicion} "
                . "de la sucursal {$sucursal->suc_nombre}."
            );
        }

        return $caja;
    }

    /** CPP sin IVA del repuesto en el depósito del que salió (para el margen). */
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
}

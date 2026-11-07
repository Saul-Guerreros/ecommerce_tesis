<?php

namespace App\Services\Servicio;

use App\Exceptions\EmisionFacturaException;
use App\Jobs\DeclararFacturaElectronica;
use App\Models\Compras\Stock;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\OrdenServicio;
use App\Models\Servicio\PresupuestoDetalle;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\Trabajo;
use App\Models\Usuario;
use App\Models\Venta\Caja;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaDetalle;
use App\Models\Venta\VentaFe;
use App\Services\Venta\NumeracionVentaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Facturas del servicio técnico (venta_cab con ven_sseid). Las genera el
 * USUARIO, igual que [GENERAR FACTURA] del pedido: ciclo comprobante ->
 * factura -> cobro (guía 0.54.1, cap. 29).
 *
 *   generarAnticipo()  ANTICIPO (tipo_transaccion 9): una línea por el
 *                      importe declarado, al 10 % (tratamiento de la
 *                      operación real: prestación de servicios).
 *   generarFinal()     VENTA: todas las líneas del servicio; si hay un
 *                      anticipo cobrado, se aplica POR ÍTEM (vnd_anticipo)
 *                      y la cabecera guarda el NETO. Neto cero -> Cobrada.
 *
 * Los dos comparten numeración (NumeracionVentaService), venta_fe y el job
 * DeclararFacturaElectronica, que se despacha DESPUÉS del commit.
 */
class FacturaServicioService
{
    /** Diferencia aceptada (Gs.) entre la suma de las líneas y sse_total. */
    private const TOLERANCIA = 1.0;

    private const TASA_SERVICIO = 10.0;

    public function __construct(
        private NumeracionVentaService $numeracion,
        private FacturacionServicioService $datosFiscales,
        private EstadoPagoServicioService $estadoPago,
    ) {}

    // ---------- Factura de anticipo ----------

    /**
     * Factura el anticipo DECLARADO (mostrador o web). Los comprobantes web
     * tienen que estar validados. El cobro se registra después, cuando la
     * factura ya tiene su documento (CDC, XML y PDF).
     */
    public function generarAnticipo(SolicitudServicio $s, Usuario $usuario): VentaCab
    {
        return DB::transaction(function () use ($s, $usuario) {
            $s = $this->bloquear($s);

            if (!in_array($s->codigoServicio(), EstadoPagoServicioService::ESTADOS_ANTICIPO, true)) {
                throw ValidationException::withMessages([
                    'factura' => 'La factura de anticipo se emite con el equipo recibido y antes de que esté listo.',
                ]);
            }

            $this->estadoPago->exigirQueAdmitaPagos($s);

            if ($existente = $s->facturaAnticipo()->first()) {
                throw ValidationException::withMessages([
                    'factura' => "Esta solicitud ya tiene la factura de anticipo {$existente->ven_nrodoc}.",
                ]);
            }

            $pagos = $this->pagosDeclarados($s, anticipo: true);

            if ($pagos->isEmpty()) {
                throw ValidationException::withMessages([
                    'factura' => 'No hay un anticipo declarado para facturar.',
                ]);
            }

            $this->exigirComprobantesValidados($pagos);

            $monto = round((float) $pagos->sum(fn ($p) => (float) $p->spa_monto), 2);

            $s->load(['facturacion', 'cliente.persona']);

            $sucursal = $this->resolverSucursal($s);
            $lineas   = [$this->lineaAnticipo($s, $monto)];
            $totales  = $this->calcularTotales($lineas);

            $venta = $this->emitir($s, $usuario, $sucursal, VentaCab::TIPO_ANTICIPO, $lineas, $totales);

            $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                'Factura de anticipo %s emitida — Gs. %s',
                $venta->ven_nrodoc,
                number_format($monto, 0, ',', '.'),
            ));

            return $venta;
        });
    }

    // ---------- Factura final ----------

    /**
     * Factura final del servicio LISTO. Los pagos declarados del saldo
     * (no rechazados, sin contar el anticipo) tienen que sumar el NETO.
     */
    public function generarFinal(SolicitudServicio $s, Usuario $usuario): VentaCab
    {
        return DB::transaction(function () use ($s, $usuario) {
            $s = $this->bloquear($s);

            if ($s->codigoServicio() !== EstadoServicio::LISTA) {
                throw ValidationException::withMessages([
                    'factura' => 'La factura final se genera con el equipo LISTO.',
                ]);
            }

            if ($s->sse_garant || (float) $s->sse_total <= 0) {
                throw ValidationException::withMessages([
                    'factura' => 'Este servicio no genera factura (garantía o sin monto a cobrar).',
                ]);
            }

            if ($existente = $s->factura()->first()) {
                throw ValidationException::withMessages([
                    'factura' => "Esta solicitud ya tiene la factura {$existente->ven_nrodoc}.",
                ]);
            }

            $anticipo = $this->anticipoParaAplicar($s);

            $s->load([
                'ordenServicio.repuestos.producto.tipoIva',
                'ordenServicio.repuestos.producto.unidadMedida',
                'facturacion',
                'cliente.persona',
            ]);

            $sucursal = $this->resolverSucursal($s);
            $lineas   = $this->construirLineas($s);
            $this->verificarTotal($s, $this->calcularTotales($lineas)['total']);

            $aplicado = 0.0;

            if ($anticipo) {
                [$lineas, $aplicado] = $this->aplicarAnticipo($lineas, $anticipo);
            }

            $totales = $this->calcularTotales($lineas);

            $pagos = $this->pagosDeclarados($s, anticipo: false);
            $this->exigirComprobantesValidados($pagos);
            $this->exigirPagosDelNeto($pagos, $totales['total'], $aplicado);

            $venta = $this->emitir($s, $usuario, $sucursal, VentaCab::TIPO_VENTA, $lineas, $totales, $anticipo, $aplicado);

            // Neto cero: el anticipo cubrió todo. No hay nada que cobrar.
            if ($totales['total'] <= 0.01) {
                $venta->update(['ven_estado' => VentaCab::ESTADO_COBRADA]);
            }

            $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                'Factura %s emitida — Gs. %s%s',
                $venta->ven_nrodoc,
                number_format($totales['total'], 0, ',', '.'),
                $aplicado > 0 ? ' (anticipo aplicado Gs. ' . number_format($aplicado, 0, ',', '.') . ')' : '',
            ));

            return $venta;
        });
    }

    // ---------- Emisión común ----------

    /** Toma el número, guarda cabecera + detalle y programa la declaración. */
    private function emitir(
        SolicitudServicio $s,
        Usuario $usuario,
        Sucursal $sucursal,
        string $tipo,
        array $lineas,
        array $totales,
        ?VentaCab $anticipo = null,
        float $aplicado = 0.0,
    ): VentaCab {
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
            'ven_obs'    => ($tipo === VentaCab::TIPO_ANTICIPO ? 'Anticipo servicio técnico ' : 'Servicio técnico ')
                          . $s->sse_numero,
        ]);

        $venta->ven_sseid    = $s->sse_id;
        $venta->ven_tipo     = $tipo;
        $venta->ven_antvenid = $anticipo?->ven_id;
        $venta->ven_antapl   = round($aplicado, 2);
        $venta->save();

        foreach ($lineas as $linea) {
            VentaDetalle::create(['vnd_venid' => $venta->ven_id] + $linea);
        }

        $this->programarDeclaracion($venta);

        return $venta->load('detalle');
    }

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

    // ---------- Anticipo ----------

    /**
     * Anticipo que la final puede aplicar. Bloquea si hay un anticipo
     * declarado sin facturar, o facturado pero sin cobrar o sin aprobar
     * (en producción PREPARADO no alcanza: guía 29).
     */
    private function anticipoParaAplicar(SolicitudServicio $s): ?VentaCab
    {
        $anticipo = $s->facturaAnticipo()->with(['fe', 'detalle'])->lockForUpdate()->first();

        if (!$anticipo) {
            if ($this->pagosDeclarados($s, anticipo: true)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'factura' => 'Hay un anticipo declarado sin factura: emití su factura de anticipo o rechazá ese pago antes de generar la final.',
                ]);
            }

            return null;
        }

        if (config('facturacion.activa') && !$anticipo->fe?->estaEnviado()) {
            throw ValidationException::withMessages([
                'factura' => "La factura de anticipo {$anticipo->ven_nrodoc} todavía no está aprobada por la DNIT. "
                    . 'La final se genera cuando el anticipo esté aprobado.',
            ]);
        }

        if ($anticipo->ven_estado !== VentaCab::ESTADO_COBRADA) {
            throw ValidationException::withMessages([
                'factura' => "La factura de anticipo {$anticipo->ven_nrodoc} todavía no está cobrada.",
            ]);
        }

        // Acreditado por completo con nota de crédito: no queda nada que aplicar.
        return $anticipo->disponibleAnticipo() > 0.01 ? $anticipo : null;
    }

    /**
     * Aplica el anticipo POR ÍTEM (guía 29, condicion 2): solo líneas de la
     * MISMA tasa que el anticipo (no se trasladan importes entre tasas),
     * primero las de cantidad 1 (sin decimales por unidad). Lo que no
     * alcanza a aplicarse queda disponible para devolverlo con NC.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function aplicarAnticipo(array $lineas, VentaCab $anticipo): array
    {
        $tasa      = (float) ($anticipo->detalle->first()?->vnd_tasa ?? self::TASA_SERVICIO);
        $restante  = $anticipo->disponibleAnticipo();
        $aplicado  = 0.0;

        $orden = array_keys(array_filter($lineas, fn ($l) => (float) $l['vnd_tasa'] == $tasa));

        usort($orden, function ($a, $b) use ($lineas) {
            $ua = (float) $lineas[$a]['vnd_cantidad'] == 1.0 ? 0 : 1;
            $ub = (float) $lineas[$b]['vnd_cantidad'] == 1.0 ? 0 : 1;

            return [$ua, $lineas[$a]['vnd_nroitem']] <=> [$ub, $lineas[$b]['vnd_nroitem']];
        });

        foreach ($orden as $i) {
            if ($restante <= 0.009) {
                break;
            }

            $cantidad = (float) $lineas[$i]['vnd_cantidad'];
            $precio   = (float) $lineas[$i]['vnd_precio'];

            $aplicar = min($restante, round($cantidad * $precio, 2));
            $unidad  = min(round($aplicar / $cantidad, 4), $precio);
            $real    = round($unidad * $cantidad, 2);

            if ($real <= 0) {
                continue;
            }

            $neto = round($cantidad * ($precio - $unidad), 2);

            $lineas[$i]['vnd_anticipo']   = $unidad;
            $lineas[$i]['vnd_valorventa'] = $neto;
            $lineas[$i]['vnd_iva']        = $this->ivaPorDentro($neto, (float) $lineas[$i]['vnd_tasa']);

            $aplicado += $real;
            $restante  = round($restante - $real, 2);
        }

        if ($aplicado <= 0) {
            throw ValidationException::withMessages([
                'factura' => 'El anticipo no se puede aplicar: la factura no tiene líneas con la misma tasa de IVA que el anticipo.',
            ]);
        }

        return [$lineas, round($aplicado, 2)];
    }

    private function lineaAnticipo(SolicitudServicio $s, float $monto): array
    {
        return [
            'vnd_prdid'       => null,
            'vnd_nroitem'     => 1,
            'vnd_codigo'      => 'ANTICIPO',
            'vnd_descripcion' => mb_substr("Anticipo - Servicio técnico {$s->sse_numero}", 0, 150),
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

    // ---------- Pagos declarados ----------

    /** Pagos no rechazados del anticipo o del saldo. */
    private function pagosDeclarados(SolicitudServicio $s, bool $anticipo): Collection
    {
        return $s->pagos()
            ->with(['metodoPago', 'estadoPago', 'comprobante'])
            ->get()
            ->filter(fn (ServicioPago $p) => $p->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO
                                          && $p->esAnticipo() === $anticipo)
            ->values();
    }

    /** No se factura lo que no se miró: comprobantes web validados. */
    private function exigirComprobantesValidados(Collection $pagos): void
    {
        foreach ($pagos as $p) {
            if ($p->spa_canal !== ServicioPago::CANAL_WEB || !$p->metodoPago?->mpa_requcom) {
                continue;
            }

            if (!$p->comprobante?->sco_verifi) {
                throw ValidationException::withMessages([
                    'factura' => "Validá el comprobante del pago {$p->spa_orden} antes de generar la factura.",
                ]);
            }
        }
    }

    private function exigirPagosDelNeto(Collection $pagos, float $neto, float $aplicado): void
    {
        $declarado = round((float) $pagos->sum(fn ($p) => (float) $p->spa_monto), 2);
        $gs        = fn ($m) => number_format($m, 0, ',', '.');

        if ($neto <= 0.01 && $pagos->isEmpty()) {
            return;
        }

        if (abs($declarado - $neto) > 0.01) {
            throw ValidationException::withMessages([
                'pagos' => "Los pagos declarados del saldo suman Gs. {$gs($declarado)} y la factura final queda en Gs. {$gs($neto)}"
                    . ($aplicado > 0 ? " (anticipo aplicado Gs. {$gs($aplicado)})" : '')
                    . '. Declará los medios por el saldo exacto.',
            ]);
        }
    }

    // ---------- Líneas del servicio ----------

    /** @return array<int, array<string, mixed>> */
    private function construirLineas(SolicitudServicio $s): array
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

    // ---------- Totales ----------

    private function ivaPorDentro(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa), 2) : 0.0;
    }

    /** @return array<string, float> */
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

    /** Las líneas BRUTAS (antes del anticipo) tienen que dar sse_total. */
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

    // ---------- Configuración fiscal ----------

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

    /** Caja activa del local: aporta el punto de expedición (también en transferencias). */
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

    private function bloquear(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()
            ->with(['estadoServicio', 'ordenServicio'])->firstOrFail();
    }
}
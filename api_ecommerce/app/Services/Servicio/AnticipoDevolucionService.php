<?php

namespace App\Services\Servicio;

use App\Models\Compras\MotivoNotaCredito;
use App\Models\Pedido\MetodoPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Models\Venta\DevolucionVenta;
use App\Models\Venta\NotaCreditoVenta;
use App\Services\Venta\NotaCreditoVentaService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Devolución de un anticipo facturado (guía 0.54.1, cap. 30). Dos hechos
 * separados, en este orden:
 *
 *   1. acreditar()            NC (motivo DANT, asociada al CDC del anticipo)
 *                             por lo NO aplicado. Corrige el documento fiscal.
 *   2. registrarDevolucion()  salida real del dinero (caja o transferencia),
 *                             respaldada por esa NC ya aprobada.
 *
 * Una cancelación local no prueba nada ante la DNIT, y borrar un cobro no
 * es devolver: por eso son dos registros propios.
 */
class AnticipoDevolucionService
{
    public function __construct(
        private NotaCreditoVentaService $notas,
        private SolicitudServicioService $solicitudes,
        private ServicioEstadoService $estados,
    ) {}

    // ---------- 1. Nota de crédito ----------

    /** @param array{monto?: ?int, observacion?: ?string} $d */
    public function acreditar(SolicitudServicio $s, array $d, Usuario $usuario): NotaCreditoVenta
    {
        return DB::transaction(function () use ($s, $d, $usuario) {
            $s        = $this->bloquear($s);
            $anticipo = $s->facturaAnticipo()->with('detalle')->lockForUpdate()->first();

            if (!$anticipo) {
                throw ValidationException::withMessages(['nota_credito' => 'Esta solicitud no tiene factura de anticipo.']);
            }

            $disponible = $anticipo->disponibleAnticipo();

            if ($disponible <= 0.01) {
                throw ValidationException::withMessages([
                    'nota_credito' => 'El anticipo ya está aplicado o acreditado por completo: no queda importe por acreditar.',
                ]);
            }

            $monto = round((float) ($d['monto'] ?? $disponible), 2);

            if ($monto <= 0 || $monto > $disponible + 0.01) {
                throw ValidationException::withMessages([
                    'monto' => 'El importe a acreditar no puede superar lo disponible del anticipo (Gs. '
                        . $this->gs($disponible) . ').',
                ]);
            }

            $motivo = MotivoNotaCredito::activos()
                ->where('mnc_codigo', MotivoNotaCredito::DEVOLUCION_ANTICIPO)
                ->first();

            if (!$motivo) {
                throw ValidationException::withMessages([
                    'nota_credito' => 'Falta el motivo "Devolución de anticipo" (DANT). Ejecutá las migraciones.',
                ]);
            }

            $linea = $anticipo->detalle->first();

            try {
                $nota = $this->notas->store([
                    'ncv_venid' => $anticipo->ven_id,
                    'ncv_mncid' => $motivo->mnc_id,
                    'ncv_fecha' => now()->toDateString(),
                    'ncv_obs'   => $d['observacion'] ?? "Devolución de anticipo - Servicio técnico {$s->sse_numero}",
                ], [[
                    'vnd_id'   => $linea->vnd_id,
                    'cantidad' => 1,
                    'precio'   => $monto,
                ]], $usuario);
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['nota_credito' => $e->getMessage()]);
            }

            $this->estados->registrarNota($s, $usuario->usu_id, sprintf(
                'Nota de crédito %s por Gs. %s sobre el anticipo %s',
                $nota->ncv_nrodoc,
                $this->gs($monto),
                $anticipo->ven_nrodoc,
            ));

            return $nota;
        });
    }

    // ---------- 2. Devolución del dinero ----------

    /**
     * @param array{mpa_codigo: string, monto: int, nro_transaccion: ?string, banco: ?string,
     *              fecha: ?string, observacion: ?string} $d
     */
    public function registrarDevolucion(SolicitudServicio $s, int $notaId, array $d, Usuario $usuario): DevolucionVenta
    {
        return DB::transaction(function () use ($s, $notaId, $d, $usuario) {
            $s    = $this->bloquear($s);
            $nota = $this->notaDelServicio($s, $notaId);

            if ($nota->anulada()) {
                throw ValidationException::withMessages(['devolucion' => 'La nota de crédito está anulada.']);
            }

            if (config('facturacion.activa') && !$nota->fe?->estaEnviado()) {
                throw ValidationException::withMessages([
                    'devolucion' => "La nota de crédito {$nota->ncv_nrodoc} todavía no está aprobada por la DNIT. "
                        . 'La devolución se registra cuando lo esté.',
                ]);
            }

            $metodo = MetodoPago::porCodigo($d['mpa_codigo']);

            // El efectivo sale de la caja de la sucursal del equipo.
            if ($metodo->mpa_codigo === MetodoPago::CONTRAENTREGA) {
                $this->validarSucursal($s, $usuario);
            }

            if ($metodo->mpa_requcom && blank($d['nro_transaccion'] ?? null)) {
                throw ValidationException::withMessages([
                    'nro_transaccion' => 'Ingresá la referencia de la transferencia o el giro de la devolución.',
                ]);
            }

            $pendiente = $nota->porDevolver();
            $monto     = (float) $d['monto'];

            if ($pendiente <= 0.01) {
                throw ValidationException::withMessages([
                    'monto' => 'No queda dinero por devolver contra esta nota de crédito.',
                ]);
            }

            if ($monto > $pendiente + 0.01) {
                throw ValidationException::withMessages([
                    'monto' => 'La devolución no puede superar Gs. ' . $this->gs($pendiente)
                        . ' (lo que queda de la nota de crédito y del dinero recibido).',
                ]);
            }

            $devolucion = DevolucionVenta::create([
                'dev_ncvid'  => $nota->ncv_id,
                'dev_mpaid'  => $metodo->mpa_id,
                'dev_monto'  => $monto,
                'dev_nrotrx' => $d['nro_transaccion'] ?? null,
                'dev_banco'  => $d['banco'] ?? null,
                'dev_fecha'  => $d['fecha'] ?? now()->toDateString(),
                'dev_observ' => $d['observacion'] ?? null,
                'dev_usuid'  => $usuario->usu_id,
                'dev_sucid'  => $s->sse_sucid ?? $nota->factura->ven_sucid,
                'dev_estado' => DevolucionVenta::ESTADO_ACTIVA,
            ]);

            $this->estados->registrarNota($s, $usuario->usu_id, sprintf(
                'Devolución registrada — %s Gs. %s (NC %s sobre la factura %s)',
                $metodo->mpa_descri,
                $this->gs($monto),
                $nota->ncv_nrodoc,
                $nota->factura->ven_nrodoc,
            ));

            return $devolucion;
        });
    }

    /** Una devolución mal cargada no se edita: se anula y se registra la correcta. */
    public function anularDevolucion(SolicitudServicio $s, int $devolucionId, string $motivo, Usuario $usuario): void
    {
        DB::transaction(function () use ($s, $devolucionId, $motivo, $usuario) {
            $s = $this->bloquear($s);

            $dev = DevolucionVenta::with('notaCredito.factura')->lockForUpdate()->find($devolucionId);

            abort_if(!$dev || $dev->notaCredito?->factura?->ven_sseid !== $s->sse_id, 404,
                'La devolución no pertenece a esta solicitud.');

            if ($dev->dev_estado !== DevolucionVenta::ESTADO_ACTIVA) {
                throw ValidationException::withMessages(['devolucion' => 'Esta devolución ya está anulada.']);
            }

            $dev->update([
                'dev_estado' => DevolucionVenta::ESTADO_ANULADA,
                'dev_fecanu' => now(),
                'dev_anusid' => $usuario->usu_id,
                'dev_motanu' => $motivo,
            ]);

            $this->estados->registrarNota($s, $usuario->usu_id, sprintf(
                'Devolución de Gs. %s anulada — %s',
                $this->gs((float) $dev->dev_monto),
                $motivo,
            ));
        });
    }
    
    /** Libera el anticipo de una final de neto cero (no admite NC). */
    public function liberarAnticipo(SolicitudServicio $s, string $motivo, Usuario $usuario): void
    {
        app(\App\Services\Venta\AnticipoAplicacionService::class)->liberarManual($s, $motivo, $usuario);
    }

    // ---------- Helpers ----------

    /** NC de cualquiera de las dos facturas del servicio (anticipo o final). */
    private function notaDelServicio(SolicitudServicio $s, int $notaId): NotaCreditoVenta
    {
        $nota = NotaCreditoVenta::with(['factura', 'fe'])->lockForUpdate()->find($notaId);

        abort_if(
            !$nota || $nota->factura?->ven_sseid !== $s->sse_id,
            404,
            'La nota de crédito no corresponde a una factura de esta solicitud.'
        );

        return $nota;
    }

    private function validarSucursal(SolicitudServicio $s, Usuario $usuario): void
    {
        $permitida = $this->solicitudes->sucursalesMostrador($usuario)->contains('suc_id', $s->sse_sucid);

        if (!$s->sse_sucid || !$permitida) {
            throw ValidationException::withMessages([
                'mpa_codigo' => 'El efectivo se devuelve en la sucursal del equipo, por un cajero asignado a ella.',
            ]);
        }
    }

    private function bloquear(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with('estadoServicio')->firstOrFail();
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}
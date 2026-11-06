<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Servicio\EntregaServicio;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\PresupuestoServicio;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobro PRESENCIAL del servicio (canal MOSTRADOR del pago). Lo usa quien
 * tenga servicios.cobros.confirmar (el permiso lo exige la ruta; sin él no
 * se registran cobros - decisión 12-a).
 *
 * El cobro queda CONFIRMADO en el acto: el funcionario recibió el dinero o
 * vio la transferencia/giro. Por eso siempre guarda quién cobró y en qué
 * sucursal (también lo exige ck_spa_mostrador).
 *
 *   - ANTICIPO: desde RECIBIDA hasta antes de LISTA, opcional y sin mínimo
 *     (6-a). Tope: lo aprobado en presupuestos, si ya hay alguno.
 *   - SALDO / TOTAL: en LISTA, hasta el saldo pendiente.
 *   - "Cobrar y entregar" (A5): cobro del saldo + entrega en una transacción.
 */
class CobroMostradorService
{
    /** Estados en los que se acepta un anticipo (antes de LISTA, fuera de garantía). */
    private const ESTADOS_ANTICIPO = [
        EstadoServicio::RECIBIDA,
        EstadoServicio::EN_DIAGNOSTICO,
        EstadoServicio::PRESUPUESTADA,
        EstadoServicio::APROBADA,
        EstadoServicio::EN_REPARACION,
    ];

    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private RegistroPagoServicio $registro,
        private SolicitudServicioService $solicitudes,
        private EntregaServicioService $entregas,
    ) {}

    /**
     * Registra un cobro presencial ya confirmado.
     *
     * @param array{mpa_codigo: string, monto: int, nro_transaccion: ?string, banco: ?string,
     *              fecha_pago: ?string, observacion: ?string, comprobante?: ?\Illuminate\Http\UploadedFile} $d
     */
    public function registrarCobro(SolicitudServicio $s, array $d, Usuario $usuario): SolicitudServicio
    {
        $archivos = [];

        try {
            return DB::transaction(function () use ($s, $d, $usuario, &$archivos) {
                $s = $this->bloquear($s);

                $this->estadoPago->exigirQueAdmitaPagos($s);
                $this->validarSucursal($s, $usuario);

                $metodo = MetodoPago::porCodigo($d['mpa_codigo']);
                $monto  = (int) $d['monto'];

                $this->validarReferencia($metodo, $d);
                $tope = $this->tope($s);

                if ($tope !== null && $monto > $tope) {
                    throw ValidationException::withMessages([
                        'monto' => 'El monto supera lo pendiente de cobro (Gs. ' . $this->gs($tope) . ').',
                    ]);
                }

                $total = (int) round((float) $s->sse_total);
                $this->crearConfirmado($s, $metodo, $monto, $d, $usuario, $archivos,
                    unicoQueCubreTodo: $this->estadoPago->consolidado($s)
                        && $monto === $total && $this->estadoPago->resumen($s)['comprometido'] === 0);

                $consolidado = $this->estadoPago->consolidado($s);
                $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                    '%s cobrado en mostrador — %s Gs. %s',
                    $consolidado ? 'Pago' : 'Anticipo',
                    $metodo->mpa_descri,
                    $this->gs($monto),
                ));

                return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'estadoPago']);
            });
        } catch (\Throwable $e) {
            $this->registro->borrarArchivos($archivos);
            throw $e;
        }
    }

    /**
     * El cliente eligió en la web "pagar al retirar" y ahora paga en efectivo:
     * el cajero confirma ESE pago y queda como quien cobró. Si el cliente
     * decide pagar de otra forma, el pago se rechaza (queda libre) y se
     * registra un cobro nuevo.
     */
    public function confirmarAlRetirar(SolicitudServicio $s, int $pagoId, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $pagoId, $usuario) {
            $s = $this->bloquear($s);
            $this->validarSucursal($s, $usuario);

            $pago = ServicioPago::where('spa_id', $pagoId)->where('spa_sseid', $s->sse_id)
                ->lockForUpdate()->with(['estadoPago', 'metodoPago'])->first();

            abort_if(!$pago, 404, 'El pago no pertenece a esta solicitud.');

            if ($pago->metodoPago?->mpa_codigo !== MetodoPago::CONTRAENTREGA
                || $pago->estadoPago?->epa_codigo !== EstadoPago::PAGO_VERIFICAR) {
                throw ValidationException::withMessages([
                    'pago' => 'Solo se confirma así un pago "al retirar" que esté en verificación.',
                ]);
            }

            $pago->update([
                'spa_epaid'   => EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
                'spa_montver' => $pago->spa_monto,
                'spa_fechpag' => now()->toDateString(),
                'spa_usuver'  => $usuario->usu_id,
                'spa_fechver' => now(),
                // canal sigue WEB (lo declaró el cliente), pero queda quién cobró y dónde
                'spa_usucob'  => $usuario->usu_id,
                'spa_sucid'   => $s->sse_sucid,
            ]);

            $this->estadoPago->recalcular($s, $usuario->usu_id,
                "Pago {$pago->spa_orden} (al retirar) cobrado en mostrador — Gs. " . $this->gs((float) $pago->spa_monto));

            return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'estadoPago']);
        });
    }

    /**
     * A5 "Cobrar y entregar": cobra el saldo completo y registra la entrega
     * en UNA transacción. Si la entrega falla (accesorios sin justificar,
     * receptor sin C.I.), el cobro tampoco queda registrado.
     *
     * La regla 7 no cambia: EntregaServicioService::registrar() vuelve a
     * exigir CONFIRMADO; acá solo se garantiza que el cobro cubra el saldo.
     *
     * @param array $cobro   mismos campos que registrarCobro() (sin monto: es el saldo)
     * @param array $entrega mismos campos que el modal A5
     */
    public function cobrarYEntregar(SolicitudServicio $s, array $cobro, array $entrega, Usuario $usuario): EntregaServicio
    {
        return DB::transaction(function () use ($s, $cobro, $entrega, $usuario) {
            $s = $this->bloquear($s);

            if ($s->codigoServicio() !== EstadoServicio::LISTA) {
                throw ValidationException::withMessages(['pago' => 'Solo se cobra y entrega un equipo LISTO.']);
            }

            $r = $this->estadoPago->resumen($s);

            if ($r['en_verificacion'] > 0) {
                throw ValidationException::withMessages([
                    'pago' => 'Hay pagos en verificación: confirmalos o rechazalos antes de cobrar el saldo.',
                ]);
            }

            if ($r['saldo'] > 0) {
                $s = $this->registrarCobro($s, $cobro + ['monto' => $r['saldo']], $usuario);
            }

            // Con el estado recalculado, registrar() vuelve a aplicar la regla 7.
            return $this->entregas->registrar($s->fresh(['estadoServicio', 'estadoPago']), $entrega, $usuario);
        });
    }

    // ---------- Reglas ----------

    /**
     * Máximo cobrable ahora. NULL = sin tope (anticipo sin presupuesto
     * aprobado todavía: decisión 6-a, sin mínimo ni máximo fijado).
     */
    private function tope(SolicitudServicio $s): ?int
    {
        $r = $this->estadoPago->resumen($s);

        if ($r['consolidado']) {
            if ($r['saldo'] <= 0) {
                throw ValidationException::withMessages([
                    'monto' => $r['en_verificacion'] > 0
                        ? 'El saldo está cubierto por pagos en verificación.'
                        : 'Este servicio no tiene saldo pendiente.',
                ]);
            }

            return $r['saldo'];
        }

        if (!in_array($s->codigoServicio(), self::ESTADOS_ANTICIPO, true)) {
            throw ValidationException::withMessages([
                'monto' => 'El anticipo se registra con el equipo recibido y antes de que esté listo.',
            ]);
        }

        $aprobado = (int) round((float) PresupuestoServicio::query()
            ->where('pse_oseid', $s->ordenServicio?->ose_id ?? 0)
            ->whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::APROBADO))
            ->sum('pse_total'));

        if ($aprobado <= 0) {
            return null;
        }

        // Los presupuestos complementarios (v2+) solo traen lo adicional: se suman.
        $disponible = $aprobado + (int) round((float) $s->sse_cosret) - $r['comprometido'];

        if ($disponible <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El anticipo ya cubre todo lo aprobado en el presupuesto.',
            ]);
        }

        return $disponible;
    }

    /** El cobro se hace en la sucursal del equipo y el funcionario tiene que estar asignado a ella. */
    private function validarSucursal(SolicitudServicio $s, Usuario $usuario): void
    {
        $permitida = $this->solicitudes->sucursalesMostrador($usuario)->contains('suc_id', $s->sse_sucid);

        if (!$s->sse_sucid || !$permitida) {
            throw ValidationException::withMessages([
                'pago' => 'Solo se cobra en la sucursal donde está el equipo y en la que usted está asignado.',
            ]);
        }
    }

    /** Transferencia y giro Tigo: sin la referencia no hay forma de rastrear el dinero. */
    private function validarReferencia(MetodoPago $metodo, array $d): void
    {
        if ($metodo->mpa_requcom && blank($d['nro_transaccion'] ?? null)) {
            throw ValidationException::withMessages([
                'nro_transaccion' => $metodo->mpa_codigo === MetodoPago::BILLETERA_MOVIL
                    ? 'Ingrese la referencia del giro.'
                    : 'Ingrese el número de la transferencia.',
            ]);
        }
    }

    private function crearConfirmado(SolicitudServicio $s, MetodoPago $metodo, int $monto, array $d, Usuario $usuario, array &$archivos, bool $unicoQueCubreTodo): void
    {
        $this->registro->crear($s, [
            'metodo'      => $metodo,
            'monto'       => $monto,
            'canal'       => ServicioPago::CANAL_MOSTRADOR,
            'epaid'       => EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
            'comprobante' => $this->registro->guardarArchivo($s, $d['comprobante'] ?? null, $archivos),
            'nrotrx'      => $d['nro_transaccion'] ?? null,
            'banco'       => $d['banco'] ?? null,
            'fechpag'     => $d['fecha_pago'] ?? now()->toDateString(),
            'observ'      => $d['observacion'] ?? null,
            'montver'     => $monto,
            'usuver'      => $usuario->usu_id,
            'fechver'     => now(),
            'usucob'      => $usuario->usu_id,
            'sucid'       => $s->sse_sucid,
        ], $unicoQueCubreTodo);
    }

    private function bloquear(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()
            ->with(['estadoServicio', 'ordenServicio'])->firstOrFail();
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}

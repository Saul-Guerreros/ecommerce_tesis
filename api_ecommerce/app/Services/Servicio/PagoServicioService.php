<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioComprobante;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Services\Pedido\CheckoutPagoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Canal WEB del cobro del servicio (C4 - /mis-servicios/:numero).
 *
 *   - registrarAnticipo(): con el equipo recibido y antes de LISTA, UN
 *     anticipo por transferencia o billetera, con comprobante.
 *   - registrar(): en LISTA, el SALDO (total − anticipo − lo ya declarado).
 *
 * Todo pago nace en PAGO_VERIFICAR: el admin valida el comprobante, genera
 * la factura (de anticipo o final) y registra el cobro.
 */
class PagoServicioService
{
    public const MODO_UNICO    = 'unico';
    public const MODO_DIVIDIDO = 'dividido';

    public function __construct(
        private CheckoutPagoService $configuracion,
        private EstadoPagoServicioService $estadoPago,
        private RegistroPagoServicio $registro,
        private SolicitudServicioService $solicitudes,
    ) {}

    // ---------- Lectura ----------

    /**
     * Datos de la pantalla de pago: los mismos de la tienda (banco, Tigo,
     * mensaje de efectivo) + el resumen de montos y los pagos ya hechos.
     */
    public function datosIniciales(Usuario $usuario, string $numero): array
    {
        $s = $this->solicitudes->detalleDelCliente($usuario, $numero);

        return $this->configuracion->datosIniciales() + [
            'resumen'  => $this->estadoPago->resumen($s),
            'anticipo' => $this->estadoPago->anticipoWeb($s),
            'pagos'    => $s->pagos()->with(['metodoPago', 'estadoPago', 'comprobante', 'motivoRechazo'])->get(),
        ];
    }

    // ---------- Escritura ----------

       /**
     * Anticipo web: transferencia o billetera con comprobante. El monto lo
     * elige el cliente, con tope en lo aprobado del presupuesto (si hay).
     *
     * @param array{mpa_codigo: string, monto: int, comprobante: UploadedFile} $datos
     */
    public function registrarAnticipo(Usuario $usuario, string $numero, array $datos): SolicitudServicio
    {
        $archivos = [];

        try {
            return DB::transaction(function () use ($usuario, $numero, $datos, &$archivos) {
                $s = $this->bloquear($this->solicitudes->detalleDelCliente($usuario, $numero));

                $this->estadoPago->exigirQueAdmitaPagos($s);

                if (!in_array($s->codigoServicio(), EstadoPagoServicioService::ESTADOS_ANTICIPO, true)) {
                    throw ValidationException::withMessages([
                        'pago' => 'El anticipo se paga con el equipo ya recibido y antes de que esté listo.',
                    ]);
                }

                $this->estadoPago->exigirSinAnticipo($s);

                $metodo = MetodoPago::porCodigo($datos['mpa_codigo']);

                if (!$metodo->mpa_requcom) {
                    throw ValidationException::withMessages([
                        'mpa_codigo' => 'El anticipo por la web se paga por transferencia o billetera, con comprobante.',
                    ]);
                }

                $monto = (int) $datos['monto'];
                $tope  = $this->estadoPago->topeAnticipo($s);

                if ($monto <= 0 || ($tope !== null && $monto > $tope)) {
                    throw ValidationException::withMessages([
                        'monto' => $tope !== null
                            ? 'El anticipo tiene que ser mayor a cero y no superar Gs. ' . $this->gs($tope) . '.'
                            : 'El anticipo tiene que ser mayor a cero.',
                    ]);
                }

                $pago = $this->armarPago($s, $metodo->mpa_codigo, $monto, $datos['comprobante'] ?? null, $archivos);

                $this->registro->crear($s, [
                    'metodo'      => $pago['metodo'],
                    'monto'       => $pago['monto'],
                    'canal'       => ServicioPago::CANAL_WEB,
                    'epaid'       => EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id,
                    'tipo'        => ServicioPago::TIPO_ANTICIPO,
                    'comprobante' => $pago['comprobante'],
                ]);

                $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                    'Cliente declaró un anticipo de Gs. %s (%s)',
                    $this->gs($monto),
                    $metodo->mpa_descri,
                ));

                return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'estadoPago']);
            });
        } catch (\Throwable $e) {
            $this->registro->borrarArchivos($archivos);
            throw $e;
        }
    }

    /**
     * El cliente sube un comprobante nuevo para un pago rechazado. Igual que
     * en el pedido: opera sobre UN pago y el resto queda intacto.
     */
    public function reemplazarComprobante(Usuario $usuario, string $numero, int $pagoId, UploadedFile $archivo): SolicitudServicio
    {
        $rutaNueva = null;
        $rutaAnterior = null;

        try {
            $s = DB::transaction(function () use ($usuario, $numero, $pagoId, $archivo, &$rutaNueva, &$rutaAnterior) {
                $s    = $this->bloquear($this->solicitudes->detalleDelCliente($usuario, $numero));
                $pago = $this->pagoRechazable($s, $pagoId);

                $rutaAnterior = $pago->comprobante?->sco_archiv;
                $rutaNueva    = $archivo->store("servicios/{$s->sse_id}/comprobantes", 'public');

                // El pago vuelve a cero: sin rastros de la verificación anterior.
                $pago->update([
                    'spa_epaid'   => EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id,
                    'spa_mrpid'   => null,
                    'spa_observ'  => null,
                    'spa_montver' => null,
                    'spa_usuver'  => null,
                    'spa_fechver' => null,
                ]);

                $pago->comprobante
                    ? $pago->comprobante->update(['sco_archiv' => $rutaNueva, 'sco_verifi' => false])
                    : ServicioComprobante::create(['sco_spaid' => $pago->spa_id, 'sco_archiv' => $rutaNueva, 'sco_verifi' => false]);

                $this->estadoPago->recalcular($s, $usuario->usu_id,
                    "Cliente cargó un comprobante nuevo para el Pago {$pago->spa_orden}");

                return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'estadoPago']);
            });
        } catch (\Throwable $e) {
            if ($rutaNueva) {
                Storage::disk('public')->delete($rutaNueva);
            }
            throw $e;
        }

        // Recién confirmada la transacción se borra el archivo viejo.
        if ($rutaAnterior && $rutaAnterior !== $rutaNueva) {
            Storage::disk('public')->delete($rutaAnterior);
        }

        return $s;
    }

    // ---------- Guards ----------

    /**
     * Saldo que la web puede pagar ahora. Exige LISTA, que admita pagos y
     * que no haya nada esperando verificación ni corrección: el cliente
     * paga el saldo completo de una vez (como el total del pedido).
     */
    private function saldoPagable(SolicitudServicio $s): int
    {
        $this->estadoPago->exigirQueAdmitaPagos($s);

        if ($s->codigoServicio() !== EstadoServicio::LISTA) {
            throw ValidationException::withMessages([
                'pago' => 'El pago se habilita cuando tu equipo está listo.',
            ]);
        }

        $r = $this->estadoPago->resumen($s);

        if ($r['saldo'] <= 0) {
            throw ValidationException::withMessages([
                'pago' => $r['en_verificacion'] > 0
                    ? 'Tu pago está en verificación. Te avisamos apenas lo confirmemos.'
                    : 'Este servicio no tiene saldo pendiente.',
            ]);
        }

        return $r['saldo'];
    }

    private function pagoRechazable(SolicitudServicio $s, int $pagoId): ServicioPago
    {
        $pago = ServicioPago::where('spa_id', $pagoId)
            ->where('spa_sseid', $s->sse_id)
            ->lockForUpdate()
            ->first();

        abort_if(!$pago, 404, 'El pago no pertenece a esta solicitud.');

        $pago->load(['estadoPago', 'metodoPago', 'comprobante']);

        if (in_array($s->codigoServicio(), EstadoServicio::FINALES, true)) {
            throw ValidationException::withMessages(['pago' => 'La solicitud está cerrada: ya no admite comprobantes.']);
        }

        if ($pago->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO) {
            throw ValidationException::withMessages(['pago' => 'Sólo se puede reemplazar el comprobante de un pago rechazado.']);
        }

        if (!$pago->metodoPago?->mpa_requcom) {
            throw ValidationException::withMessages(['pago' => 'Este método de pago no lleva comprobante.']);
        }

        return $pago;
    }

    // ---------- Armado ----------

    private function armarPago(SolicitudServicio $s, string $codigo, int $monto, ?UploadedFile $archivo, array &$archivos): array
    {
        $metodo = MetodoPago::porCodigo($codigo);

        if ($metodo->mpa_requcom && !$archivo) {
            throw ValidationException::withMessages(['comprobante' => 'Adjuntá el comprobante del pago.']);
        }

        return [
            'metodo'      => $metodo,
            'monto'       => $monto,
            'comprobante' => $metodo->mpa_requcom ? $this->registro->guardarArchivo($s, $archivo, $archivos) : null,
        ];
    }

    /** La suma tiene que cubrir exactamente el saldo (igual que ped_total en el checkout). */
    private function armarDividido(SolicitudServicio $s, array $pagos, int $saldo, array &$archivos): array
    {
        $asignado = array_sum(array_map(fn ($p) => (int) ($p['monto'] ?? 0), $pagos));

        if ($asignado !== $saldo) {
            throw ValidationException::withMessages([
                'pagos' => $asignado > $saldo
                    ? 'El total asignado (Gs. ' . $this->gs($asignado) . ') supera el saldo (Gs. ' . $this->gs($saldo) . ').'
                    : 'Todavía queda un pendiente de Gs. ' . $this->gs($saldo - $asignado) . '. Asigná el saldo completo.',
            ]);
        }

        // foreach y no array_map con fn(): una arrow function captura $archivos
        // por valor y las rutas guardadas no llegarían al rollback.
        $resultado = [];
        foreach (array_values($pagos) as $p) {
            $resultado[] = $this->armarPago($s, $p['mpa_codigo'], (int) $p['monto'], $p['comprobante'] ?? null, $archivos);
        }

        return $resultado;
    }

    // ---------- Helpers ----------

    /** SELECT ... FOR UPDATE: dos envíos simultáneos se serializan. */
    private function bloquear(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with(['estadoServicio', 'ordenServicio'])->firstOrFail();
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}

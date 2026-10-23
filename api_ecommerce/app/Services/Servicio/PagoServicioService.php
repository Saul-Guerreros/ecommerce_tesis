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
 * Espejo de PagoPedidoService, con las reglas del servicio:
 *
 *   - Solo se paga en LISTA y por el SALDO pendiente (decisión 7-b): sin
 *     anticipos por la web (lo impide también ck_spa_anticipo).
 *   - Los pagos quedan en PAGO_VERIFICAR. El efectivo ("pagar al retirar",
 *     CONTRAENTREGA) lo confirma el cajero en el mostrador al cobrarlo.
 *   - Sin plazo (decisión 10-a): el equipo queda en el taller, no hay
 *     reserva que liberar ni barrido de expiración.
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
            'resumen' => $this->estadoPago->resumen($s),
            'pagos'   => $s->pagos()->with(['metodoPago', 'estadoPago', 'comprobante', 'motivoRechazo'])->get(),
        ];
    }

    // ---------- Escritura ----------

    /**
     * @param array $datos $request->validated() con el mismo formato que el
     *                     checkout: modo, mpa_codigo, comprobante | pagos[]
     */
    public function registrar(Usuario $usuario, string $numero, array $datos): SolicitudServicio
    {
        $archivos = [];

        try {
            return DB::transaction(function () use ($usuario, $numero, $datos, &$archivos) {
                $s = $this->bloquear($this->solicitudes->detalleDelCliente($usuario, $numero));
                $saldo = $this->saldoPagable($s);

                $esDividido = ($datos['modo'] ?? self::MODO_UNICO) === self::MODO_DIVIDIDO;

                $pagos = $esDividido
                    ? $this->armarDividido($s, $datos['pagos'] ?? [], $saldo, $archivos)
                    : [$this->armarPago($s, $datos['mpa_codigo'], $saldo, $datos['comprobante'] ?? null, $archivos)];

                $verificar = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id;
                $cubreTodo = count($pagos) === 1 && $saldo === (int) round((float) $s->sse_total);

                foreach ($pagos as $p) {
                    $this->registro->crear($s, [
                        'metodo'      => $p['metodo'],
                        'monto'       => $p['monto'],
                        'canal'       => ServicioPago::CANAL_WEB,
                        'epaid'       => $verificar,
                        'comprobante' => $p['comprobante'],
                    ], $cubreTodo);
                }

                $this->estadoPago->recalcular($s, $usuario->usu_id, count($pagos) > 1
                    ? 'Cliente declaró su pago dividido en ' . count($pagos) . ' pagos'
                    : 'Cliente declaró su pago: ' . $pagos[0]['metodo']->mpa_descri);

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
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with('estadoServicio')->firstOrFail();
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}

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
 * Canal WEB del cobro del servicio (C4).
 *   - registrarAnticipo(): antes de LISTA, UN anticipo (transferencia, billetera o cheque).
 *   - registrar(): en LISTA, el SALDO en un pago o dividido.
 * Cheque -> PENDIENTE_PAGO + servicio_cheque EN_ESPERA; el resto -> PAGO_VERIFICAR.
 */
class PagoServicioService
{
    public const MODO_UNICO    = 'unico';
    public const MODO_DIVIDIDO = 'dividido';

    public const RELACIONES_CLIENTE = ['metodoPago', 'estadoPago', 'comprobante', 'cheque', 'motivoRechazo'];

    public function __construct(
        private CheckoutPagoService $configuracion,
        private EstadoPagoServicioService $estadoPago,
        private RegistroPagoServicio $registro,
        private SolicitudServicioService $solicitudes,
        private PoliticaChequeServicio $politicaCheque,
    ) {}

    public function datosIniciales(Usuario $usuario, string $numero): array
    {
        $s     = $this->solicitudes->detalleDelCliente($usuario, $numero);
        $datos = $this->configuracion->datosIniciales();

        // En el servicio el cheque no depende de la modalidad de entrega:
        // el papel siempre se acerca a la sucursal donde está el equipo.
        $datos['cheque_habilitado'] = $this->politicaCheque->admite($s);
        $datos['cheque_motivo']     = $this->politicaCheque->motivo($s);  

        if (!$datos['cheque_habilitado']) {
            $datos['metodos'] = $datos['metodos']
                ->reject(fn ($m) => $m->mpa_codigo === MetodoPago::CHEQUE)
                ->values();
        }

        return $datos + [
            'resumen'  => $this->estadoPago->resumen($s),
            'anticipo' => $this->estadoPago->anticipoWeb($s),
            'pagos'    => $s->pagos()->with(self::RELACIONES_CLIENTE)->get(),
        ];
    }

    public function registrar(Usuario $usuario, string $numero, array $datos): SolicitudServicio
    {
        $archivos = [];

        try {
            return DB::transaction(function () use ($usuario, $numero, $datos, &$archivos) {
                $s     = $this->bloquear($this->solicitudes->detalleDelCliente($usuario, $numero));
                $saldo = $this->saldoPagable($s);

                $esDividido = ($datos['modo'] ?? self::MODO_UNICO) === self::MODO_DIVIDIDO;

                $pagos = $esDividido
                    ? $this->armarDividido($s, $datos['pagos'] ?? [], $saldo, $archivos)
                    : [$this->armarPago($s, $datos['mpa_codigo'], $saldo, $datos['comprobante'] ?? null,
                        $datos['cheque'] ?? null, $archivos)];

                $this->politicaCheque->exigir($s, array_column($pagos, 'metodo'));        
                $cubreTodo = count($pagos) === 1 && $saldo === (int) round((float) $s->sse_total);

                foreach ($pagos as $p) {
                    $this->crearPagoWeb($s, $p, null, $cubreTodo);
                }

                $this->estadoPago->recalcular($s, $usuario->usu_id, count($pagos) > 1
                    ? 'Cliente declaró el saldo dividido en ' . count($pagos) . ' pagos'
                    : 'Cliente declaró su pago: ' . $pagos[0]['metodo']->mpa_descri);

                return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'pagos.cheque', 'estadoPago']);
            });
        } catch (\Throwable $e) {
            $this->registro->borrarArchivos($archivos);
            throw $e;
        }
    }

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
                $this->politicaCheque->exigir($s, [$metodo]);

                if (!$metodo->mpa_requcom && $metodo->mpa_codigo !== MetodoPago::CHEQUE) {
                    throw ValidationException::withMessages([
                        'mpa_codigo' => 'El anticipo por la web se paga por transferencia, billetera o cheque.',
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

                $pago = $this->armarPago($s, $metodo->mpa_codigo, $monto, $datos['comprobante'] ?? null,
                    $datos['cheque'] ?? null, $archivos);

                $this->crearPagoWeb($s, $pago, ServicioPago::TIPO_ANTICIPO);

                $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                    'Cliente declaró un anticipo de Gs. %s (%s)', $this->gs($monto), $metodo->mpa_descri,
                ));

                return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'pagos.cheque', 'estadoPago']);
            });
        } catch (\Throwable $e) {
            $this->registro->borrarArchivos($archivos);
            throw $e;
        }
    }

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

                return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'pagos.cheque', 'estadoPago']);
            });
        } catch (\Throwable $e) {
            if ($rutaNueva) {
                Storage::disk('public')->delete($rutaNueva);
            }
            throw $e;
        }

        if ($rutaAnterior && $rutaAnterior !== $rutaNueva) {
            Storage::disk('public')->delete($rutaAnterior);
        }

        return $s;
    }

    /** Exige LISTA, que admita pagos y que quede saldo sin comprometer (un cheque en espera ya lo compromete). */
    private function saldoPagable(SolicitudServicio $s): int
    {
        $this->estadoPago->exigirQueAdmitaPagos($s);

        if ($s->codigoServicio() !== EstadoServicio::LISTA) {
            throw ValidationException::withMessages(['pago' => 'El pago se habilita cuando tu equipo está listo.']);
        }

        $r = $this->estadoPago->resumen($s);

        if ($r['saldo'] <= 0) {
            throw ValidationException::withMessages([
                'pago' => match (true) {
                    $r['en_verificacion'] > 0  => 'Tu pago está en verificación. Te avisamos apenas lo confirmemos.',
                    $r['cheque_pendiente'] > 0 => 'Tu pago con cheque está declarado: acercá el cheque a la sucursal dentro del plazo.',
                    default                    => 'Este servicio no tiene saldo pendiente.',
                },
            ]);
        }

        return $r['saldo'];
    }

    private function pagoRechazable(SolicitudServicio $s, int $pagoId): ServicioPago
    {
        $pago = ServicioPago::where('spa_id', $pagoId)->where('spa_sseid', $s->sse_id)->lockForUpdate()->first();

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

    private function armarPago(SolicitudServicio $s, string $codigo, int $monto, ?UploadedFile $archivo, ?array $cheque, array &$archivos): array
    {
        $metodo = MetodoPago::porCodigo($codigo);

        if ($metodo->mpa_requcom && !$archivo) {
            throw ValidationException::withMessages(['comprobante' => 'Adjuntá el comprobante del pago.']);
        }

        $datosCheque = $this->registro->normalizarCheque($metodo, $cheque);

        if ($metodo->mpa_codigo === MetodoPago::CHEQUE && !$datosCheque) {
            throw ValidationException::withMessages(['cheque' => 'Completá los datos del cheque.']);
        }

        return [
            'metodo'      => $metodo,
            'monto'       => $monto,
            'comprobante' => $metodo->mpa_requcom ? $this->registro->guardarArchivo($s, $archivo, $archivos) : null,
            'cheque'      => $datosCheque,
        ];
    }

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

        $resultado = [];
        foreach (array_values($pagos) as $p) {
            $resultado[] = $this->armarPago($s, $p['mpa_codigo'], (int) $p['monto'], $p['comprobante'] ?? null,
                $p['cheque'] ?? null, $archivos);
        }

        return $resultado;
    }

    private function crearPagoWeb(SolicitudServicio $s, array $p, ?string $tipo = null, bool $cubreTodo = false): ServicioPago
    {
        $estado = $p['cheque'] ? EstadoPago::PENDIENTE_PAGO : EstadoPago::PAGO_VERIFICAR;

        return $this->registro->crear($s, [
            'metodo'      => $p['metodo'],
            'monto'       => $p['monto'],
            'canal'       => ServicioPago::CANAL_WEB,
            'epaid'       => EstadoPago::porCodigo($estado)->epa_id,
            'tipo'        => $tipo,
            'comprobante' => $p['comprobante'],
            'cheque'      => $p['cheque'],
        ], $cubreTodo);
    }

    private function bloquear(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with(['estadoServicio', 'ordenServicio'])->firstOrFail();
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}
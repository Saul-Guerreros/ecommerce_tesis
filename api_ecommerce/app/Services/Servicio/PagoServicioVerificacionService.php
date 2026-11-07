<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioComprobante;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Models\Venta\VentaCab;
use App\Services\Facturacion\FacturaCorreoService;
use App\Services\Venta\CobroVentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Venta\CobroVenta;
use Throwable;

/**
 * Verificación y cobro de los pagos del servicio. Espejo de
 * PagoVerificacionService del pedido (comprobante -> factura -> cobro):
 *
 *   1. validarComprobante()  marca el comprobante; no mueve dinero.
 *   2. (la factura la genera FacturaServicioService)
 *   3. registrarCobro()      cobro_venta contra la factura (anticipo o
 *                            final), solo si ya tiene su documento (CDC,
 *                            XML y PDF). Si queda Cobrada, se programa el
 *                            correo (guía 6.6.A.4).
 *   rechazar()               solo mientras el pago no esté facturado.
 */
class PagoServicioVerificacionService
{
    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private NotificadorServicio $notificador,
        private CobroVentaService $cobros,
        private FacturaCorreoService $correo,
        private RegistroPagoServicio $registro,
        private SolicitudServicioService $solicitudes,
    ) {}

    // ---------- 1. Validar comprobante ----------

    /** @param array{observacion: ?string} $datos */
    public function validarComprobante(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            $s    = $this->bloquearSolicitud($s);
            $pago = $this->bloquearPago($s, $pagoId);

            $this->validarOperable($s);
            $this->validarPendiente($pago);

            $comprobante = $pago->comprobante;

            if (!$comprobante) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este pago no tiene ningún comprobante cargado para validar.',
                ]);
            }

            if ($comprobante->sco_verifi) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este comprobante ya fue validado.',
                ]);
            }

            $comprobante->update([
                'sco_verifi'  => true,
                'sco_observ'  => $datos['observacion'] ?? null,
                'sco_usuver'  => $usuario->usu_id,
                'sco_fechver' => now(),
            ]);

            $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                'Comprobante del pago %d validado — cubre Gs. %s',
                $pago->spa_orden,
                $this->gs((float) $pago->spa_monto),
            ));

            return $this->fresco($s);
        });
    }

    // ---------- 3. Registrar el cobro ----------

    /**
     * @param array{monto_verificado: float, nro_transaccion: ?string, banco: ?string, fecha_pago: ?string,
     *              observacion: ?string, confirmar_diferencia: ?bool,
     *              comprobante?: ?\Illuminate\Http\UploadedFile} $datos
     */
    public function registrarCobro(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): SolicitudServicio
    {
        $archivos = [];

        try {
            return DB::transaction(function () use ($s, $pagoId, $datos, $usuario, &$archivos) {
                $s    = $this->bloquearSolicitud($s);
                $pago = $this->bloquearPago($s, $pagoId);

                $this->validarOperable($s);
                $this->validarPendiente($pago);
                $this->validarComprobanteOReferencia($pago, $datos);
                $this->validarMonto($pago, $datos);

                // Efectivo y mostrador: lo cobra un cajero de la sucursal del equipo.
                $enCaja = $pago->spa_canal === ServicioPago::CANAL_MOSTRADOR
                    || $pago->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA;

                if ($enCaja) {
                    $this->validarSucursal($s, $usuario);
                }

                $factura = $this->facturaDelPago($s, $pago);
                $this->exigirDocumento($factura);

                // Mostrador: el cajero puede adjuntar el comprobante que tiene a la vista.
                $ruta = !$pago->comprobante
                    ? $this->registro->guardarArchivo($s, $datos['comprobante'] ?? null, $archivos)
                    : null;

                if ($ruta) {
                    ServicioComprobante::create([
                        'sco_spaid'   => $pago->spa_id,
                        'sco_archiv'  => $ruta,
                        'sco_verifi'  => true,
                        'sco_usuver'  => $usuario->usu_id,
                        'sco_fechver' => now(),
                    ]);
                }

                $this->cobros->registrar($factura, $pago, $datos, $usuario);

                $pago->update([
                    'spa_epaid' => EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
                    'spa_mrpid' => null,
                ] + ($enCaja ? ['spa_usucob' => $usuario->usu_id, 'spa_sucid' => $s->sse_sucid] : []));

                $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                    'Cobro del pago %d registrado — Gs. %s (factura %s)',
                    $pago->spa_orden,
                    $this->gs((float) $datos['monto_verificado']),
                    $factura->ven_nrodoc,
                ));

                if ($factura->refresh()->ven_estado === VentaCab::ESTADO_COBRADA) {
                    $this->correo->programar($factura);
                }

                return $this->fresco($s);
            });
        } catch (Throwable $e) {
            $this->registro->borrarArchivos($archivos);
            throw $e;
        }
    }

    // ---------- Rechazar ----------

    /** @param array{motivo_id: int, detalle: ?string, monto_verificado: ?float} $datos */
    public function rechazar(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            $s    = $this->bloquearSolicitud($s);
            $pago = $this->bloquearPago($s, $pagoId);

            $this->validarOperable($s);
            $this->validarPendiente($pago);
            $this->validarSinFactura($s, $pago);

            $motivo = MotivoRechazoPago::activos()->findOrFail($datos['motivo_id']);

            if ($motivo->mrp_requdet && blank($datos['detalle'] ?? null)) {
                throw ValidationException::withMessages(['detalle' => 'Este motivo exige un detalle para el cliente.']);
            }

            $detalle = $datos['detalle'] ?? $motivo->mrp_descri;

            $pago->update([
                'spa_epaid'   => EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
                'spa_mrpid'   => $motivo->mrp_id,
                'spa_observ'  => $detalle,
                'spa_montver' => $datos['monto_verificado'] ?? null,
                'spa_usuver'  => $usuario->usu_id,
                'spa_fechver' => now(),
            ]);

            $pago->comprobante?->update([
                'sco_verifi'  => false,
                'sco_observ'  => $detalle,
                'sco_usuver'  => $usuario->usu_id,
                'sco_fechver' => now(),
            ]);

            $this->estadoPago->recalcular($s, $usuario->usu_id,
                "Pago {$pago->spa_orden} rechazado — {$motivo->mrp_descri}");

            if ($pago->metodoPago?->mpa_requcom && $pago->spa_canal === ServicioPago::CANAL_WEB) {
                $this->notificador->alCambiarPago($s, EstadoPago::RECHAZADO, $detalle);
            }

            return $this->fresco($s);
        });
    }

    // ---------- Factura del pago ----------

    /** El anticipo se cobra contra su factura; el resto, contra la final. */
    private function facturaDelPago(SolicitudServicio $s, ServicioPago $pago): VentaCab
    {
        $factura = $pago->esAnticipo()
            ? $s->facturaAnticipo()->lockForUpdate()->first()
            : $s->factura()->lockForUpdate()->first();

        if (!$factura) {
            throw ValidationException::withMessages([
                'cobro' => $pago->esAnticipo()
                    ? 'Emití la factura de anticipo antes de registrar el cobro.'
                    : 'Generá la factura del servicio antes de registrar el cobro.',
            ]);
        }

        return $factura;
    }

    /**
     * Antes de cobrar: CDC y archivos guardados (guía 6.6.A: un 202 o un
     * PENDIENTE sin archivos no alcanza). Sin facturación electrónica activa
     * no hay documento que esperar.
     */
    private function exigirDocumento(VentaCab $factura): void
    {
        if (!config('facturacion.activa')) {
            return;
        }

        $fe = $factura->fe()->first();

        if ($fe?->tieneDocumento()) {
            return;
        }

        throw ValidationException::withMessages([
            'cobro' => $fe?->estaRechazado()
                ? "La DNIT rechazó la factura {$factura->ven_nrodoc}: no se cobra hasta resolverla."
                : "La factura {$factura->ven_nrodoc} todavía no tiene su documento electrónico (CDC, XML y PDF). "
                  . 'Esperá a que termine la emisión y volvé a intentar.',
        ]);
    }

    /** Con factura emitida el pago ya está declarado ante la DNIT: corresponde NC. */
    private function validarSinFactura(SolicitudServicio $s, ServicioPago $pago): void
    {
        $factura = $pago->esAnticipo() ? $s->facturaAnticipo()->first() : $s->factura()->first();

        if ($factura) {
            throw ValidationException::withMessages([
                'pago' => "El pago ya está declarado en la factura {$factura->ven_nrodoc}: no se rechaza. "
                    . 'Corresponde nota de crédito.',
            ]);
        }
    }

    // ---------- Validaciones ----------

    /**
     * Web con comprobante: tiene que estar validado. Mostrador por
     * transferencia o giro: sin la referencia no hay forma de rastrear el dinero.
     */
    private function validarComprobanteOReferencia(ServicioPago $pago, array $datos): void
    {
        if (!$pago->metodoPago?->mpa_requcom) {
            return;
        }

        if ($pago->spa_canal === ServicioPago::CANAL_WEB) {
            if (!$pago->comprobante?->sco_verifi) {
                throw ValidationException::withMessages([
                    'cobro' => 'Primero validá el comprobante de este pago. El cobro se registra después.',
                ]);
            }

            return;
        }

        if (blank($datos['nro_transaccion'] ?? null)) {
            throw ValidationException::withMessages([
                'nro_transaccion' => $pago->metodoPago->mpa_codigo === MetodoPago::BILLETERA_MOVIL
                    ? 'Ingrese la referencia del giro.'
                    : 'Ingrese el número de la transferencia.',
            ]);
        }
    }

    private function validarSucursal(SolicitudServicio $s, Usuario $usuario): void
    {
        $permitida = $this->solicitudes->sucursalesMostrador($usuario)->contains('suc_id', $s->sse_sucid);

        if (!$s->sse_sucid || !$permitida) {
            throw ValidationException::withMessages([
                'pago' => 'Solo se cobra en la sucursal donde está el equipo y en la que usted está asignado.',
            ]);
        }
    }

    private function bloquearSolicitud(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with('estadoServicio')->firstOrFail();
    }

    private function bloquearPago(SolicitudServicio $s, int $pagoId): ServicioPago
    {
        $pago = ServicioPago::where('spa_id', $pagoId)
            ->where('spa_sseid', $s->sse_id)
            ->lockForUpdate()
            ->first();

        abort_if(!$pago, 404, 'El pago no pertenece a esta solicitud.');

        return $pago->load(['estadoPago', 'metodoPago', 'comprobante']);
    }

    private function validarOperable(SolicitudServicio $s): void
    {
        if ($s->codigoServicio() === EstadoServicio::CANCELADA) {
            throw ValidationException::withMessages(['pago' => 'La solicitud está cancelada: sus pagos ya no se pueden operar.']);
        }
    }

    private function validarPendiente(ServicioPago $pago): void
    {
        $actual = $pago->estadoPago?->epa_codigo;

        if ($actual !== EstadoPago::PAGO_VERIFICAR) {
            throw ValidationException::withMessages([
                'pago' => match ($actual) {
                    EstadoPago::CONFIRMADO => 'Este pago ya fue cobrado. Para revertirlo hay que anular el cobro y emitir una nota de crédito.',
                    EstadoPago::RECHAZADO  => 'Este pago ya fue rechazado: espera un comprobante nuevo del cliente.',
                    default                => 'Este pago no está pendiente.',
                },
            ]);
        }
    }

    /** De menos se rechaza; de más se confirma explícitamente. */
    private function validarMonto(ServicioPago $pago, array $datos): void
    {
        $esperado   = (float) $pago->spa_monto;
        $verificado = (float) $datos['monto_verificado'];

        if (abs($esperado - $verificado) < 0.01) {
            return;
        }

        if ($verificado < $esperado) {
            throw ValidationException::withMessages([
                'monto_verificado' => "Se recibieron Gs. {$this->gs($verificado)} de los Gs. {$this->gs($esperado)} declarados. "
                    . 'Un cobro menor no se registra: el pago ya está declarado en la factura por ese importe.',
            ]);
        }

        if (empty($datos['confirmar_diferencia'])) {
            throw ValidationException::withMessages([
                'monto_verificado' => "Lo recibido excede en Gs. {$this->gs($verificado - $esperado)} el monto declarado. Confirmá el excedente para continuar.",
            ]);
        }
    }

    private function fresco(SolicitudServicio $s): SolicitudServicio
    {
        return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'pagos.cobro', 'estadoPago']);
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }

        // ---------- Anular un cobro ----------

    /**
     * Revierte un cobro mal cargado. El pago vuelve a PAGO_VERIFICAR: sigue
     * declarado en la factura y se cobra de nuevo con los datos correctos.
     * Con el equipo ENTREGADO no se anula: se corrige con nota de crédito.
     */
    public function anularCobro(SolicitudServicio $s, int $cobroId, string $motivo, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $cobroId, $motivo, $usuario) {
            $s = $this->bloquearSolicitud($s);

            if ($s->codigoServicio() === EstadoServicio::ENTREGADA) {
                throw ValidationException::withMessages([
                    'cobro' => 'El servicio ya se entregó: un cobro mal cargado se corrige con nota de crédito.',
                ]);
            }

            $cobro = CobroVenta::with('pagoServicio')->whereKey($cobroId)->lockForUpdate()->first();

            abort_if(!$cobro || $cobro->pagoServicio?->spa_sseid !== $s->sse_id, 404,
                'El cobro no pertenece a esta solicitud.');

            $factura = $this->cobros->anular($cobro, $motivo, $usuario);

            $cobro->pagoServicio->update([
                'spa_epaid' => EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id,
            ]);

            $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                'Cobro de Gs. %s anulado (factura %s) — %s',
                $this->gs((float) $cobro->cob_monto),
                $factura->ven_nrodoc,
                $motivo,
            ));

            return $this->fresco($s);
        });
    }
}
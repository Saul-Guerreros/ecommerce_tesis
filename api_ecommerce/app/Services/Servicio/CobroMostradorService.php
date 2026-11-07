<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Servicio\EntregaServicio;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Models\Venta\VentaCab;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operaciones PRESENCIALES del cobro del servicio (servicios.cobros.confirmar).
 *
 * El cajero DECLARA los medios (intención, PAGO_VERIFICAR) y en la misma
 * operación se emite la factura. El dinero se registra después con
 * PagoServicioVerificacionService::registrarCobro(), cuando la factura ya
 * tiene su documento (guía 29: "factura antes de registrar el cobro").
 *
 *   declararAnticipo()  medios del anticipo + factura de anticipo.
 *   generarFactura()    medios del saldo (opcional) + factura final.
 *   cobrarYEntregar()   A5: exige factura final; cobra el pendiente y entrega.
 */
class CobroMostradorService
{
    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private RegistroPagoServicio $registro,
        private SolicitudServicioService $solicitudes,
        private FacturaServicioService $facturas,
        private PagoServicioVerificacionService $verificacion,
        private EntregaServicioService $entregas,
    ) {}

    /** @param array{pagos: array<int, array{mpa_codigo: string, monto: int}>} $d */
    public function declararAnticipo(SolicitudServicio $s, array $d, Usuario $usuario): VentaCab
    {
        return DB::transaction(function () use ($s, $d, $usuario) {
            $s = $this->bloquear($s);

            if (!in_array($s->codigoServicio(), EstadoPagoServicioService::ESTADOS_ANTICIPO, true)) {
                throw ValidationException::withMessages([
                    'pagos' => 'El anticipo se registra con el equipo recibido y antes de que esté listo.',
                ]);
            }

            $this->estadoPago->exigirQueAdmitaPagos($s);
            $this->validarSucursal($s, $usuario);
            $this->estadoPago->exigirSinAnticipo($s);

            $medios = $this->armarMedios($d['pagos'] ?? []);

            if ($medios === []) {
                throw ValidationException::withMessages(['pagos' => 'Indique el medio y el monto del anticipo.']);
            }

            $monto = array_sum(array_column($medios, 'monto'));
            $tope  = $this->estadoPago->topeAnticipo($s);

            if ($tope !== null && $monto > $tope) {
                throw ValidationException::withMessages([
                    'pagos' => 'El anticipo supera lo aprobado en el presupuesto (Gs. ' . $this->gs($tope) . ').',
                ]);
            }

            $this->crearDeclarados($s, $medios, $usuario, ServicioPago::TIPO_ANTICIPO);

            return $this->facturas->generarAnticipo($s, $usuario);
        });
    }

    /**
     * Factura final. Si el cliente paga en el local, el cajero declara los
     * medios por el SALDO exacto (total − anticipo − pagos web ya declarados).
     * Sin medios: la factura usa los pagos web ya validados.
     *
     * @param array{pagos?: array<int, array{mpa_codigo: string, monto: int}>} $d
     */
    public function generarFactura(SolicitudServicio $s, array $d, Usuario $usuario): VentaCab
    {
        return DB::transaction(function () use ($s, $d, $usuario) {
            $s = $this->bloquear($s);

            if ($s->codigoServicio() !== EstadoServicio::LISTA) {
                throw ValidationException::withMessages(['factura' => 'La factura final se genera con el equipo LISTO.']);
            }

            $medios = $this->armarMedios($d['pagos'] ?? []);

            if ($medios !== []) {
                $this->validarSucursal($s, $usuario);

                $saldo    = $this->estadoPago->resumen($s)['saldo'];
                $asignado = array_sum(array_column($medios, 'monto'));

                if ($asignado !== $saldo) {
                    throw ValidationException::withMessages([
                        'pagos' => 'Los medios suman Gs. ' . $this->gs($asignado)
                            . ' y el saldo pendiente es Gs. ' . $this->gs($saldo) . '.',
                    ]);
                }

                $this->crearDeclarados($s, $medios, $usuario);
            }

            return $this->facturas->generarFinal($s, $usuario);
        });
    }

    /**
     * A5 "Cobrar y entregar": exige la factura final. Cobra el ÚNICO pago
     * pendiente (por su monto declarado) y registra la entrega en la misma
     * transacción. Con varios pendientes, se cobran uno por uno.
     *
     * @param array $cobro   nro_transaccion, banco, fecha_pago, observacion, comprobante
     * @param array $entrega campos del modal A5
     */
    public function cobrarYEntregar(SolicitudServicio $s, array $cobro, array $entrega, Usuario $usuario): EntregaServicio
    {
        return DB::transaction(function () use ($s, $cobro, $entrega, $usuario) {
            $s = $this->bloquear($s);

            if ($s->codigoServicio() !== EstadoServicio::LISTA) {
                throw ValidationException::withMessages(['pago' => 'Solo se cobra y entrega un equipo LISTO.']);
            }

            if ((float) $s->sse_total > 0 && !$s->sse_garant && !$s->factura()->exists()) {
                throw ValidationException::withMessages(['pago' => 'Generá la factura final antes de cobrar y entregar.']);
            }

            $pendientes = $s->pagos()
                ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', EstadoPago::PAGO_VERIFICAR))
                ->get();

            if ($pendientes->count() > 1) {
                throw ValidationException::withMessages([
                    'pago' => 'Hay varios pagos pendientes: registrá cada cobro antes de entregar.',
                ]);
            }

            if ($pago = $pendientes->first()) {
                $s = $this->verificacion->registrarCobro($s, $pago->spa_id,
                    $cobro + ['monto_verificado' => (float) $pago->spa_monto], $usuario);
            }

            return $this->entregas->registrar($s->fresh(['estadoServicio', 'estadoPago']), $entrega, $usuario);
        });
    }

    // ---------- Helpers ----------

    /** @return array<int, array{metodo: MetodoPago, monto: int}> */
    private function armarMedios(array $pagos): array
    {
        $medios = [];

        foreach (array_values($pagos) as $p) {
            $monto = (int) ($p['monto'] ?? 0);

            if ($monto <= 0) {
                throw ValidationException::withMessages(['pagos' => 'Cada medio de pago necesita un monto mayor a cero.']);
            }

            $medios[] = ['metodo' => MetodoPago::porCodigo($p['mpa_codigo']), 'monto' => $monto];
        }

        return $medios;
    }

    /** Intenciones de pago del mostrador: quién las registró y en qué sucursal. */
    private function crearDeclarados(SolicitudServicio $s, array $medios, Usuario $usuario, ?string $tipo = null): void
    {
        $verificar = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id;
        $cubreTodo = $tipo === null && count($medios) === 1
            && $medios[0]['monto'] === (int) round((float) $s->sse_total);

        foreach ($medios as $m) {
            $this->registro->crear($s, [
                'metodo' => $m['metodo'],
                'monto'  => $m['monto'],
                'canal'  => ServicioPago::CANAL_MOSTRADOR,
                'epaid'  => $verificar,
                'tipo'   => $tipo,
                'usucob' => $usuario->usu_id,
                'sucid'  => $s->sse_sucid,
            ], $cubreTodo);
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
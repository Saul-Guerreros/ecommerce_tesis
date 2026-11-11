<?php

namespace App\Services\Servicio;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Servicio\ServicioCheque;
use App\Models\Servicio\ServicioComprobante;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Alta física de servicio_pago / servicio_comprobante / servicio_cheque.
 * El cheque nace EN_ESPERA (web, 48 h) o RECIBIDO (mostrador).
 * No valida reglas de negocio ni recalcula el agregado.
 */
class RegistroPagoServicio
{
    public function __construct(private EstadoPagoServicioService $estadoPago) {}

    /**
     * @param array{metodo: MetodoPago, monto: int, canal: string, epaid: int, tipo?: ?string,
     *              comprobante?: ?string, cheque?: ?array, cheque_recibido_por?: ?int,
     *              usucob?: ?int, sucid?: ?int, observ?: ?string} $d
     */
    public function crear(SolicitudServicio $s, array $d, bool $unicoQueCubreTodo = false): ServicioPago
    {
        $pago = ServicioPago::create([
            'spa_sseid'  => $s->sse_id,
            'spa_orden'  => $this->siguienteOrden($s),
            'spa_tipo'   => $d['tipo'] ?? $this->tipo($s, $unicoQueCubreTodo),
            'spa_canal'  => $d['canal'],
            'spa_mpaid'  => $d['metodo']->mpa_id,
            'spa_monto'  => $d['monto'],
            'spa_epaid'  => $d['epaid'],
            'spa_observ' => $d['observ'] ?? null,
            'spa_usucob' => $d['usucob'] ?? null,
            'spa_sucid'  => $d['sucid'] ?? null,
        ]);

        if (!empty($d['comprobante'])) {
            ServicioComprobante::create([
                'sco_spaid'  => $pago->spa_id,
                'sco_archiv' => $d['comprobante'],
                'sco_verifi' => false,
            ]);
        }

        if (!empty($d['cheque'])) {
            $this->crearCheque($pago, $d['cheque'], $d['cheque_recibido_por'] ?? null);
        }

        return $pago;
    }

    /** null si el método no es CHEQUE. La fecha de cobro solo en el diferido. */
    public function normalizarCheque(MetodoPago $metodo, ?array $cheque): ?array
    {
        if ($metodo->mpa_codigo !== MetodoPago::CHEQUE || !$cheque) {
            return null;
        }

        $tipo = $cheque['tipo'];

        return [
            'tipo'        => $tipo,
            'banco'       => trim((string) $cheque['banco']),
            'nrodoc'      => trim((string) $cheque['nrodoc']),
            'nombre'      => trim((string) $cheque['nombre']),
            'nro'         => trim((string) ($cheque['nro'] ?? '')),
            'fecha_cobro' => $tipo === PagoCheque::TIPO_DIFERIDO ? ($cheque['fecha_cobro'] ?? null) : null,
        ];
    }

    public function guardarArchivo(SolicitudServicio $s, ?UploadedFile $archivo, array &$archivos): ?string
    {
        if (!$archivo instanceof UploadedFile) {
            return null;
        }

        $ruta = $archivo->store("servicios/{$s->sse_id}/comprobantes", 'public');
        $archivos[] = $ruta;

        return $ruta;
    }

    public function borrarArchivos(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }

    private function crearCheque(ServicioPago $pago, array $c, ?int $recibidoPor): ServicioCheque
    {
        $recibido = $recibidoPor !== null;

        return ServicioCheque::create([
            'sch_spaid'  => $pago->spa_id,
            'sch_tipo'   => $c['tipo'],
            'sch_banco'  => $c['banco'],
            'sch_nrodoc' => $c['nrodoc'],
            'sch_nombre' => $c['nombre'],
            'sch_nro'    => $c['nro'] !== '' ? $c['nro'] : null,
            'sch_fecobr' => $c['fecha_cobro'],
            'sch_estado' => $recibido ? PagoCheque::RECIBIDO : PagoCheque::EN_ESPERA,
            'sch_fevcto' => $recibido ? null : ServicioCheque::calcularVencimiento(),
            'sch_frecep' => $recibido ? now() : null,
            'sch_usurec' => $recibidoPor,
        ]);
    }

    private function siguienteOrden(SolicitudServicio $s): int
    {
        return (int) ServicioPago::where('spa_sseid', $s->sse_id)->max('spa_orden') + 1;
    }

    private function tipo(SolicitudServicio $s, bool $unicoQueCubreTodo): string
    {
        if (!$this->estadoPago->consolidado($s)) {
            return ServicioPago::TIPO_ANTICIPO;
        }

        return $unicoQueCubreTodo ? ServicioPago::TIPO_TOTAL : ServicioPago::TIPO_SALDO;
    }
}
<?php

namespace App\Services\Servicio;

use App\Models\Pedido\MetodoPago;
use App\Models\Servicio\ServicioComprobante;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Alta física de filas en servicio_pago / servicio_comprobante. La usan el
 * canal web (PagoServicioService) y el mostrador (CobroMostradorService),
 * así el número de orden, el tipo y la carpeta de los comprobantes se
 * deciden en un solo lugar.
 *
 * No valida reglas de negocio ni recalcula el agregado: eso es del service
 * que orquesta.
 */
class RegistroPagoServicio
{
    public function __construct(private EstadoPagoServicioService $estadoPago) {}

    /**
     * @param array{metodo: MetodoPago, monto: int, canal: string, epaid: int,
     *              tipo?: ?string, comprobante?: ?string, nrotrx?: ?string, banco?: ?string,
     *              fechpag?: ?string, montver?: ?int, usuver?: ?int, fechver?: mixed,
     *              usucob?: ?int, sucid?: ?int, observ?: ?string} $d
     */
    public function crear(SolicitudServicio $s, array $d, bool $unicoQueCubreTodo = false): ServicioPago
    {
        $pago = ServicioPago::create([
            'spa_sseid'   => $s->sse_id,
            'spa_orden'   => $this->siguienteOrden($s),
            'spa_tipo'    => $d['tipo'] ?? $this->tipo($s, $unicoQueCubreTodo),
            'spa_canal'   => $d['canal'],
            'spa_mpaid'   => $d['metodo']->mpa_id,
            'spa_monto'   => $d['monto'],
            'spa_epaid'   => $d['epaid'],
            'spa_observ'  => $d['observ'] ?? null,
            'spa_nrotrx'  => $d['nrotrx'] ?? null,
            'spa_banco'   => $d['banco'] ?? null,
            'spa_fechpag' => $d['fechpag'] ?? null,
            'spa_montver' => $d['montver'] ?? null,
            'spa_usuver'  => $d['usuver'] ?? null,
            'spa_fechver' => $d['fechver'] ?? null,
            'spa_usucob'  => $d['usucob'] ?? null,
            'spa_sucid'   => $d['sucid'] ?? null,
        ]);

        if (!empty($d['comprobante'])) {
            ServicioComprobante::create([
                'sco_spaid'  => $pago->spa_id,
                'sco_archiv' => $d['comprobante'],
                'sco_verifi' => ($d['montver'] ?? null) !== null,
            ]);
        }

        return $pago;
    }

    /**
     * Guarda el archivo en storage/app/public/servicios/{sse_id}/comprobantes
     * (misma raíz que AdjuntoServicio). Storage no participa del rollback:
     * la ruta se agrega a $archivos para que el llamador la borre si falla.
     */
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

    /**
     * Se calcula con lock tomado sobre la solicitud: dos cobros simultáneos
     * no pueden obtener el mismo número (además lo impide uq_spa_sse_orden).
     */
    private function siguienteOrden(SolicitudServicio $s): int
    {
        return (int) ServicioPago::where('spa_sseid', $s->sse_id)->max('spa_orden') + 1;
    }

    /**
     * ANTICIPO = antes de LISTA. TOTAL = un único pago que cubre todo el
     * total en LISTA. SALDO = cualquier otro pago en LISTA.
     */
    private function tipo(SolicitudServicio $s, bool $unicoQueCubreTodo): string
    {
        if (!$this->estadoPago->consolidado($s)) {
            return ServicioPago::TIPO_ANTICIPO;
        }

        return $unicoQueCubreTodo ? ServicioPago::TIPO_TOTAL : ServicioPago::TIPO_SALDO;
    }
}

<?php

namespace App\Services\Credito;

use App\Models\Carrito\Carrito;
use App\Models\Credito\EstadoSolicitud;
use App\Models\Credito\SolicitudCredito;
use App\Models\Credito\SolicitudCreditoDetalle;
use App\Models\Credito\SolicitudCreditoReferencia;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paso 4 del wizard: [GENERAR SOLICITUD].
 *
 * Lee el carrito a crédito (fuente de verdad de los montos, igual que
 * PedidoService lee el carrito al contado) y el borrador completo
 * (solicitud_temporal), y los funde en una sola transacción:
 * solicitud_credito + solicitud_credito_detalle + referencias.
 *
 * Por qué NO reutiliza PedidoService::resumenDeItems: ese método suma
 * TODO el carrito (incluye contado) y calcula costo de envío, que acá
 * no existe (el crédito no se entrega hasta la aprobación). Los totales
 * de crédito son más simples: solo lo que haya en car_modpag=credito.
 */
class SolicitudService
{
    public function __construct(
        private SolicitudBorradorService $borrador,
    ) {}

    /**
     * Totales del credito para el panel lateral del wizard. Los pasos 1
     * a 3 lo muestran sin necesidad de tener el borrador completo, a
     * diferencia de datosConfirmacion(), que si lo exige.
     */
    public function resumenDelCarrito(Usuario $usuario): array
    {
        return $this->resumenCredito($this->itemsCredito($usuario));
    }

    public function datosConfirmacion(Usuario $usuario): array
    {
        $temporal = $this->borrador->borradorCompletoOFallar($usuario);
        $items    = $this->itemsCredito($usuario);

        return [
            'personal'     => $temporal->stm_person,
            'laboral'      => $temporal->stm_labora,
            'referencias'  => $temporal->stm_refere,
            'resumen'      => $this->resumenCredito($items),
            'items'        => $items,
        ];
    }

    public function generar(Usuario $usuario): SolicitudCredito
    {
        $temporal = $this->borrador->borradorCompletoOFallar($usuario);
        $items    = $this->itemsCredito($usuario);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'solicitud' => 'Tu carrito a crédito está vacío. Agregá productos antes de solicitar.',
            ]);
        }

        $cliente = $usuario->persona->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        $personal = $temporal->stm_person;
        $laboral  = $temporal->stm_labora;
        $resumen  = $this->resumenCredito($items);

        return DB::transaction(function () use ($usuario, $cliente, $personal, $laboral, $temporal, $items, $resumen) {
            $solicitud = SolicitudCredito::create([
                'scr_cliid' => $cliente->cli_id,
                'scr_usuid' => $usuario->usu_id,
                'scr_escid' => EstadoSolicitud::porCodigo(EstadoSolicitud::PENDIENTE)->esc_id,

                'scr_cedula' => $personal['scr_cedula'],
                'scr_nombre' => $personal['scr_nombre'],
                'scr_apelli' => $personal['scr_apelli'],
                'scr_fechna' => $personal['scr_fechna'],
                'scr_celula' => $personal['scr_celula'],
                'scr_email'  => $personal['scr_email'],

                'scr_ciuid'   => $personal['scr_ciuid'],
                'scr_barrio'  => $personal['scr_barrio'],
                'scr_calle'   => $personal['scr_calle'],
                'scr_nrocasa' => $personal['scr_nrocasa'] ?? null,
                'scr_referen' => $personal['scr_referen'] ?? null,

                'scr_estciv' => $personal['scr_estciv'],
                'scr_tprid'  => $personal['scr_tprid'],
                'scr_proid'  => $personal['scr_proid'],
                'scr_tipviv' => $personal['scr_tipviv'],
                'scr_sertit' => $personal['scr_sertit'] ?? null,
                'scr_relsti' => $personal['scr_relsti'] ?? null,

                'scr_empres' => $laboral['scr_empres'],
                'scr_dirlab' => $laboral['scr_dirlab'],
                'scr_tellab' => $laboral['scr_tellab'],
                'scr_cargo'  => $laboral['scr_cargo'],
                'scr_ingres' => $laboral['scr_ingres'],
                'scr_fecing' => $laboral['scr_fecing'],

                'scr_toncon' => $resumen['total_contado'],
                'scr_recfin' => $resumen['recargo'],
                'scr_total'  => $resumen['total'],
                'scr_impues' => $resumen['impuestos'],
                'scr_plazo'  => $resumen['plazo'],
                'scr_cuomen' => $resumen['cuota_mensual'],

                'scr_declar' => true,
            ]);

            // Numeración correlativa a partir del id real, mismo criterio
            // que PedidoService con ped_numero.
            $solicitud->update(['scr_numero' => SolicitudCredito::numeroPara($solicitud->scr_id)]);

            $this->crearDetalle($solicitud, $items);
            $this->crearReferencias($solicitud, $temporal->stm_refere);

            // Solo se vacía la modalidad crédito: el carrito al contado
            // (si lo hay) queda intacto, mismo criterio inverso al que ya
            // usa PedidoService::confirmar con la modalidad contado.
            Carrito::where('car_idusu', $usuario->usu_id)
                ->where('car_modpag', 'credito')
                ->delete();

            $this->borrador->descartar($usuario);

            return $solicitud->fresh();
        });
    }

    // ---------- Carrito a crédito ----------

    private function itemsCredito(Usuario $usuario): Collection
    {
        return Carrito::where('car_idusu', $usuario->usu_id)
            ->where('car_modpag', 'credito')
            ->with(['producto.publicacionWeb', 'producto.tipoIva'])
            ->orderBy('car_id')
            ->get();
    }

    /**
     * Totales del "Resumen de compra a crédito": precio al contado,
     * recargo por financiación, total, impuestos, plazo y cuota mensual.
     *
     * Con planes mixtos en el carrito (un producto en 6 cuotas, otro en
     * 12), scr_plazo toma el plan MÁS LARGO y scr_cuomen es la SUMA de
     * las cuotas de cada línea para ese primer tramo — no una cuota
     * constante durante todo el plazo. Del mes 7 al 12, en el ejemplo,
     * la cuota real baja porque el producto de 6 cuotas ya se pagó. Por
     * eso en el frontend esta cifra se etiqueta "cuota mensual (primeros
     * N meses)" cuando los planes difieren: el cronograma exacto recién
     * se fija cuando el administrador aprueba la solicitud.
     */
    public function resumenCredito(Collection $items): array
    {
        $totalContado = $items->sum(fn ($i) => (float) $i->car_precio * (float) $i->car_cantid);
        $total        = $items->sum(fn ($i) => (float) $i->car_total);
        $impuestos    = $items->sum(fn ($i) => $i->producto
            ? $i->producto->ivaIncluidoEn((float) $i->car_total)
            : 0.0);

        $plazo       = (int) ($items->max('car_numcuo') ?? 0);
        $cuotaMensual = $items->sum(fn ($i) => (float) $i->car_montocuo * (float) $i->car_cantid);

        return [
            'total_contado' => round($totalContado, 2),
            'recargo'       => round($total - $totalContado, 2),
            'total'         => round($total, 2),
            'impuestos'     => round($impuestos, 2),
            'plazo'         => $plazo,
            'cuota_mensual' => round($cuotaMensual, 2),
            'planes_mixtos' => $items->pluck('car_numcuo')->unique()->count() > 1,
        ];
    }

    // ---------- Persistencia del detalle y referencias ----------

    private function crearDetalle(SolicitudCredito $solicitud, Collection $items): void
    {
        foreach ($items as $item) {
            $iva = $item->producto
                ? $item->producto->ivaIncluidoEn((float) $item->car_total)
                : 0.0;

            SolicitudCreditoDetalle::create([
                'scd_scrid'  => $solicitud->scr_id,
                'scd_prdid'  => $item->car_idpro,
                'scd_descri' => $item->producto?->prd_descri ?? 'Producto eliminado del catálogo',
                'scd_cantid' => $item->car_cantid,
                'scd_preuni' => $item->car_precio,
                'scd_numcuo' => $item->car_numcuo,
                'scd_recpct' => $item->car_recargopct,
                'scd_moncuo' => $item->car_montocuo,
                'scd_subtot' => $item->car_total,
                'scd_iva'    => round($iva, 2),
            ]);
        }
    }

    private function crearReferencias(SolicitudCredito $solicitud, array $referencias): void
    {
        foreach ($referencias as $referencia) {
            SolicitudCreditoReferencia::create([
                'scf_scrid'  => $solicitud->scr_id,
                'scf_orden'  => $referencia['scf_orden'],
                'scf_nombre' => $referencia['scf_nombre'],
                'scf_celula' => $referencia['scf_celula'],
                'scf_relaci' => $referencia['scf_relaci'],
            ]);
        }
    }

    // ---------- "Mis solicitudes" ----------

    public function listarDelCliente(Usuario $usuario)
    {
        $cliente = $usuario->persona->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        return SolicitudCredito::where('scr_cliid', $cliente->cli_id)
            ->with('estado')
            ->orderByDesc('scr_creado')
            ->get();
    }

    public function detalleDelCliente(Usuario $usuario, int $solicitudId): SolicitudCredito
    {
        $cliente = $usuario->persona->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        return SolicitudCredito::where('scr_cliid', $cliente->cli_id)
            ->with(['estado', 'ciudad.departamento', 'profesion', 'tipoProfesion', 'detalles.producto.publicacionWeb', 'referencias'])
            ->findOrFail($solicitudId);
    }
}
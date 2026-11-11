<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle completo de la solicitud para el panel admin.
 *
 * COMPONE el Resource del cliente en vez de repetir sus 40 campos: los
 * bloques personal / laboral / referencias / detalles / credito son
 * identicos en las dos pantallas, y duplicarlos garantizaria que se
 * desincronicen en el primer cambio. Acá se agrega SOLO lo que el
 * cliente no ve: capacidad de pago, evaluacion interna, cronograma e
 * historial.
 *
 * Requiere el eager loading de GestionSolicitudService::CON_DETALLE.
 */
class SolicitudAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new SolicitudCreditoResource($this->resource))->toArray($request);

        // Dato que solo interesa al evaluador: la edad al solicitar.
        $base['personal']['edad'] = $this->edad();

        $cuotaAlta = $this->cuotaMasAlta();
        $cuotaBaja = $this->cuotaMasBaja();

        return array_merge($base, [

            // ---------- Encabezado ----------
            'cliente' => [
                'cli_id' => $this->scr_cliid,
                'nombre' => $this->cliente?->persona?->nombre_completo ?: $this->nombreCompleto(),
            ],

            // ---------- Pestaña Resumen: datos clave ----------
            'analisis' => [
                'cuota_mas_alta' => $cuotaAlta,
                'cuota_mas_baja' => $cuotaBaja,

                // Sobre la cuota MAS ALTA, no sobre un promedio: es el
                // mes que mas le va a costar pagar.
                'relacion_cuota_ingreso' => $this->relacionCuotaIngreso(),
                'ingreso_mensual'        => (float) $this->scr_ingres,
                'tramos'                 => $this->tramosCuota(),
            ],

            // ---------- Pestaña Evaluacion ----------
            'evaluacion' => [
                'observaciones'    => $this->scr_observ,
                'motivo_codigo'    => $this->scr_motrec,
                'motivo'           => $this->etiquetaMotivoRechazo(),
                'detalle_rechazo'  => $this->scr_detrec,
                'fecha_decision'   => $this->scr_fecdec?->format('d/m/Y H:i'),
                'usuario_decision' => $this->usuarioDecision?->persona?->nombre_completo
                    ?: $this->usuarioDecision?->usu_login,
                'vigencia_hasta'   => $this->scr_vighas?->format('Y-m-d'),
                'oferta_vencida'   => $this->ofertaVencida(),
                'fecha_aceptacion' => $this->scr_fecace?->format('d/m/Y H:i'),
            ],

            // ---------- Pestaña Compra y plan ----------
            'cronograma' => [
                'fecha_primera_cuota' => $this->scr_fecpri?->format('Y-m-d'),

                // Cronograma firme (existe recien al aprobar o al fijar
                // la fecha). Si esta vacio, la pantalla usa 'estimado'.
                'cuotas' => SolicitudCuotaResource::collection(
                    $this->whenLoaded('cuotas')
                ),

                // Proyeccion sin fechas: deja ver como quedaria el
                // cronograma antes de comprometer nada.
                'estimado' => SolicitudCuotaResource::collection(
                    collect($this->cronogramaCalculado())
                ),
            ],

            'historial' => SolicitudHistorialResource::collection(
                $this->whenLoaded('historial')
            ),

            /**
             * Habilitacion de botones. La calcula el backend porque es
             * la misma regla que aplica el Service: si la decide el
             * componente, las dos reglas se separan y la pantalla
             * termina ofreciendo acciones que el servidor rechaza.
             */
            'acciones' => [
                'puede_tomar'      => $this->puedeTomarse(),
                'puede_decidir'    => $this->estaAbierta(),
                'puede_editar'     => $this->estaAbierta(),
            ],
        ]);
    }
}
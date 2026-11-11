<?php

namespace App\Http\Resources\Credito;

use App\Models\Credito\EstadoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud tal como la ve el CLIENTE en "Mis solicitudes de crédito".
 *
 * Compone SolicitudCreditoResource y agrega lo de la etapa D: el bloque
 * 'oferta' (plazo y acciones permitidas), el cronograma y, cuando ya se
 * generó, el pedido.
 *
 * NO se expone scr_detrec ni scr_observ: el cliente ve solo la etiqueta
 * del motivo de rechazo.
 *
 * Requiere 'estado' cargado. 'cuotas' y 'pedido' son opcionales: el
 * listado no los carga y el detalle sí.
 */
class SolicitudOfertaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base      = (new SolicitudCreditoResource($this->resource))->toArray($request);
        $rechazada = $this->estado?->esc_codigo === EstadoSolicitud::RECHAZADA;

        return array_merge($base, [

            'cronograma' => SolicitudCuotaResource::collection(
                $this->whenLoaded('cuotas')
            ),

            'pedido' => $this->whenLoaded(
                'pedido',
                fn () => $this->pedido ? ['numero' => $this->pedido->ped_numero] : null
            ),

            /**
             * Las acciones las decide el backend (misma regla que aplican
             * OfertaCreditoService y CheckoutCreditoService): si las decidiera
             * el componente, la pantalla ofrecería botones que el servidor rechaza.
             */
            'oferta' => [
                'vigencia_hasta'   => $this->scr_vighas?->format('Y-m-d'),
                'vigencia_texto'   => $this->scr_vighas?->format('d/m/Y'),
                'vigencia_dias'    => $this->vigenciaDias(),
                'dias_restantes'   => $this->diasRestantesOferta(),
                'puede_responder'  => $this->ofertaVigente(),
                'puede_continuar'  => $this->puedeContinuarCheckout(),
                'vencida'          => $this->plazoVencido(),
                'fecha_decision'   => $this->scr_fecdec?->format('d/m/Y H:i'),
                'fecha_aceptacion' => $this->scr_fecace?->format('d/m/Y H:i'),
                'motivo_rechazo'   => $rechazada ? $this->etiquetaMotivoRechazo() : null,
            ],
        ]);
    }
}
<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una linea del credito solicitado, ya congelada.
 *
 * Todos los importes salen como float y no como string decimal, para
 * que Angular los formatee con su CurrencyPipe sin castear a mano
 * (mismo criterio que PedidoListaResource con ped_total).
 *
 * cuota_mensual y recargo se calculan en el modelo y no aca: el
 * Resource serializa, no hace aritmetica de negocio.
 */
class SolicitudDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scd_id'     => $this->scd_id,
            'scd_prdid'  => $this->scd_prdid,
            'scd_descri' => $this->scd_descri,
            'scd_cantid' => (float) $this->scd_cantid,
            'scd_preuni' => (float) $this->scd_preuni,
            'scd_numcuo' => (int) $this->scd_numcuo,
            'scd_recpct' => (float) $this->scd_recpct,
            'scd_moncuo' => (float) $this->scd_moncuo,
            'scd_subtot' => (float) $this->scd_subtot,
            'scd_iva'    => (float) $this->scd_iva,

            'total_contado' => $this->totalContado(),
            'recargo'       => $this->recargo(),
            'cuota_mensual' => $this->cuotaMensualLinea(),

            // Imagen y slug: solo cuando el controlador cargo la relacion.
            // El listado de "Mis solicitudes" no las necesita y no paga el
            // costo de traerlas.
            'producto' => $this->whenLoaded('producto', function () {
                $publicacion = $this->producto->publicacionWeb;

                return $publicacion ? [
                    'pub_titulo' => $publicacion->pub_titulo,
                    'pub_slug'   => $publicacion->pub_slug,
                ] : null;
            }),
        ];
    }
}
<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Resources\Json\JsonResource;

class DatosContactoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'dco_titulo'           => $this->dco_titulo,
            'dco_intro'            => $this->dco_intro,
            'dco_direccion'        => $this->dco_direccion,
            'dco_referencia'       => $this->dco_referencia,
            'dco_telefono'         => $this->dco_telefono,
            'dco_email'            => $this->dco_email,
            'dco_whatsapp'         => $this->dco_whatsapp,
            'dco_whatsapp_mensaje' => $this->dco_whatsapp_mensaje,
            'dco_horario'          => $this->dco_horario,
            'dco_mapa_lat'         => $this->dco_mapa_lat,
            'dco_mapa_lng'         => $this->dco_mapa_lng,
            'dco_mapa_zoom'        => $this->dco_mapa_zoom,
            'dco_facebook'         => $this->dco_facebook,
            'dco_instagram'        => $this->dco_instagram,
            'dco_youtube'          => $this->dco_youtube,
            'dco_tiktok'           => $this->dco_tiktok,
            'dco_publicado'        => $this->dco_publicado,
            'dco_actualizado'      => optional($this->dco_actualizado)->format('d/m/Y H:i'),
        ];
    }
}
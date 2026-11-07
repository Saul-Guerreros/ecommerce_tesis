<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Resources\Json\JsonResource;

/** Se usa en el admin y en la tienda: no expone datos internos (usuario editor). */
class PaginaNosotrosResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'pno_titulo'      => $this->pno_titulo,
            'pno_subtitulo'   => $this->pno_subtitulo,
            'pno_historia'    => $this->pno_historia,
            'pno_mision'      => $this->pno_mision,
            'pno_vision'      => $this->pno_vision,
            'pno_valores'     => $this->pno_valores ?? [],
            'pno_cifras'      => $this->pno_cifras ?? [],
            'pno_imagen_url'  => $this->imagenUrl(),
            'pno_publicado'   => $this->pno_publicado,
            'pno_actualizado' => optional($this->pno_actualizado)->format('d/m/Y H:i'),
        ];
    }
}
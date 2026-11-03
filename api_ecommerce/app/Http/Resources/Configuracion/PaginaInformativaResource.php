<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Resources\Json\JsonResource;

class PaginaInformativaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'pgi_id'          => $this->pgi_id,
            'pgi_slug'        => $this->pgi_slug,
            'pgi_titulo'      => $this->pgi_titulo,
            'pgi_contenido'   => $this->pgi_contenido,
            'pgi_actualizado' => optional($this->pgi_actualizado)->format('d/m/Y H:i'),
        ];
    }
}
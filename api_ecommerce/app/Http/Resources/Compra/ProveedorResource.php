<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pro_id'        => $this->pro_id,
            'pro_tprid'     => $this->pro_tprid,
            'pro_ciuid'     => $this->pro_ciuid,
            'pro_ruc'       => $this->pro_ruc,
            'pro_razon'     => $this->pro_razon,
            'pro_nomfan'    => $this->pro_nomfan,
            'pro_direccion' => $this->pro_direccion,
            'pro_telef'     => $this->pro_telef,
            'pro_email'     => $this->pro_email,
            'pro_estado'    => $this->pro_estado,
            'pro_creado'    => $this->pro_creado,

            'tpr_descri' => $this->whenLoaded('tipoProveedor', fn () => $this->tipoProveedor->tpr_descri),
            'ciu_descri' => $this->whenLoaded('ciudad', fn () => $this->ciudad->ciu_descri),

            // Lista completa de contactos: se gestionan dentro del propio
            // formulario de proveedor, no en pantalla aparte.
            'contactos' => ContactoProveedorResource::collection($this->whenLoaded('contactos')),

            'contactos_count' => $this->whenNotNull($this->contactos_count),
        ];
    }
}
<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactoProveedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cpr_id'       => $this->cpr_id,
            'cpr_proid'    => $this->cpr_proid,
            'cpr_nombre'   => $this->cpr_nombre,
            'cpr_apellido' => $this->cpr_apellido,
            'cpr_cargo'    => $this->cpr_cargo,
            'cpr_telef'    => $this->cpr_telef,
            'cpr_email'    => $this->cpr_email,
            'cpr_princi'   => $this->cpr_princi,
            'cpr_estado'   => $this->cpr_estado,
        ];
    }
}
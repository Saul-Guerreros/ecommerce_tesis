<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatoFiscalClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dfi_id'        => $this->dfi_id,
            'dfi_alias'     => $this->dfi_alias,
            'dfi_numero'    => $this->dfi_numero,
            'dfi_razon'     => $this->dfi_razon,
            'dfi_telefono'  => $this->dfi_telefono,
            'dfi_correo'    => $this->dfi_correo,
            'dfi_principal' => (bool) $this->dfi_principal,
        ];
    }
}
<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacturacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $f = $this->resource;

        return [
            'tipo_documento' => $f['tipo_documento'],   // 'CI' | 'RUC'
            'dfi_id'         => $f['dfi_id']       ?? null,
            'pfa_nrodoc'     => $f['pfa_nrodoc'],
            'pfa_raznom'     => $f['pfa_raznom'],
            'pfa_telefono'   => $f['pfa_telefono'],
            'pfa_correo'     => $f['pfa_correo'],
            'pfa_recibe'     => $f['pfa_recibe'],        // 'CORREO' | 'WHATSAPP'
        ];
    }
}
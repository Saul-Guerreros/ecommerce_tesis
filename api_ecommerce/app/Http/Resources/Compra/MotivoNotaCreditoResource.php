<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotivoNotaCreditoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'mnc_id'     => $this->mnc_id,
            'mnc_codigo' => $this->mnc_codigo,
            'mnc_descri' => $this->mnc_descri,
            'mnc_afesto' => $this->mnc_afesto,
            'mnc_estado' => $this->mnc_estado,
        ];
    }
}
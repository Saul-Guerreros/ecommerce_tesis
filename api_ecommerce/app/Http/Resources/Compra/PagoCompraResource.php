<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pgo_id'      => $this->pgo_id,
            'pgo_cuoid'   => $this->pgo_cuoid,
            'pgo_fecha'   => $this->pgo_fecha?->format('Y-m-d'),
            'pgo_importe' => $this->pgo_importe,
            'pgo_formapa' => $this->pgo_formapa,
            'pgo_observ'  => $this->pgo_observ,
        ];
    }
}
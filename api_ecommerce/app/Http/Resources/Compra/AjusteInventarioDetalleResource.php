<?php
// app/Http/Resources/Compra/AjusteInventarioDetalleResource.php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AjusteInventarioDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'aid_id'      => $this->aid_id,
            'aid_prdid'   => $this->aid_prdid,
            'aid_cantsis' => $this->aid_cantsis,
            'aid_cantfis' => $this->aid_cantfis,
            'aid_costo'   => $this->aid_costo,
            'aid_obs'     => $this->aid_obs,
            'diferencia'  => $this->diferencia,

            'prd_codinv' => $this->whenLoaded('producto', fn () => $this->producto->prd_codinv),
            'prd_descri' => $this->whenLoaded('producto', fn () => $this->producto->prd_descri),
        ];
    }

    public function toArray($request)
{
    return [
        'aid_id'      => $this->aid_id,
        'aid_cantsis' => $this->aid_cantsis,
        'aid_cantfis' => $this->aid_cantfis,
        'diferencia'  => $this->aid_cantfis - $this->aid_cantsis, // ← esto es nuevo
        // ...resto de campos, incluí también la unidad de medida del producto:
        'umd_decimal' => $this->producto->unidadMedida->umd_decimal,
    ];
}
}

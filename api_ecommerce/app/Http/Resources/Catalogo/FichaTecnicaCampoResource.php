<?php

namespace App\Http\Resources\Catalogo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un campo del formulario de Ficha Técnica, tal como lo arma
 * FichaTecnicaService::campos(): definición del atributo + valor actual
 * + de dónde viene ese valor (propio o heredado del producto base).
 */
class FichaTecnicaCampoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $atributo = $this['atributo'];

        return [
            'atb_id'       => $atributo->atb_id,
            'atb_nombre'   => $atributo->atb_nombre,
            'atb_tipo'     => (int) $atributo->atb_tipo,
            'atb_unidad'   => $atributo->atb_unidad,
            'obligatorio'  => $this['obligatorio'],
            'orden'        => $this['orden'],
            'en_plantilla' => $this['en_plantilla'],

            // 'propio' | 'heredado' — en una variante solo lo propio es editable.
            'origen'   => $this['origen'],
            'editable' => $this['editable'],

            'propiedades' => $atributo->propiedades->map(fn ($p) => [
                'prp_id'     => $p->prp_id,
                'prp_nombre' => $p->prp_nombre,
            ])->values(),

            'valor_texto'   => $this['valor_texto'],
            'valor_numero'  => $this['valor_numero'],
            'propiedad_ids' => $this['propiedad_ids'],
        ];
    }
}

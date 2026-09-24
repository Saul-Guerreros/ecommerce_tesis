<?php

namespace App\Http\Requests\product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('producto');

        return [
            'prd_marid'  => ['required', 'integer', 'exists:marca,mar_id'],
            'prd_tivid'  => ['required', 'integer', 'exists:tipo_iva,tiv_id'],
            'prd_umdid'  => ['required', 'integer', 'exists:unidad_medida,umd_id'],
            'prd_codinv' => [
                'required', 'string', 'max:45',
                Rule::unique('producto', 'prd_codinv')->ignore($id, 'prd_id'),
            ],
            'prd_descri' => ['required', 'string', 'max:150'],
            'prd_stkmin' => ['required', 'numeric', 'min:0'],
            'prd_afestk' => ['nullable', 'integer', 'in:0,1'],
            'prd_estado' => ['nullable', 'integer', 'in:0,1'],

            // Variación (Sistema B: un producto = una variante). null = no
            // es variante de nada. Reglas: no puede autoreferenciarse, el
            // padre elegido no puede ser a su vez variante de otro (nada
            // de jerarquías de más de un nivel), y un producto que ya es
            // padre de otras variantes no puede pasar a ser variante él
            // mismo (evita ciclos y árboles ambiguos).
            'prd_padre_id' => [
                'nullable', 'integer',
                Rule::exists('producto', 'prd_id')->where(fn ($q) => $q->whereNull('prd_padre_id')),
                Rule::notIn([$id]),
                function ($attribute, $value, $fail) use ($id) {
                    if (!$value || !$id) {
                        return;
                    }
                    $tieneVariantesPropias = \App\Models\Producto::where('prd_padre_id', $id)->exists();
                    if ($tieneVariantesPropias) {
                        $fail('ESTE PRODUCTO YA ES MODELO BASE DE OTRAS VARIANTES; NO PUEDE A SU VEZ SER VARIANTE DE OTRO PRODUCTO.');
                    }
                },
            ],

            // Equivalencia inicial (solo alta, ver ProductoController::store).
            // No son columnas de "producto": si tiene_equiv_compra es falso
            // o no viene, no se exige nada.
            'tiene_equiv_compra' => ['nullable', 'boolean'],
            'peq_umdid' => [
                'nullable', 'integer', 'exists:unidad_medida,umd_id',
                'required_if:tiene_equiv_compra,1',
            ],
            'peq_factor' => [
                'nullable', 'numeric', 'min:0.0001',
                'required_if:tiene_equiv_compra,1',
            ],

            /**
             * Alta en lote de variantes (Opción 3): filas que se crean como
             * productos hijos de ESTE producto en la misma transacción,
             * además de lo que ya tenga (ver ProductoController::store/
             * update). prd_marid/prd_tivid/prd_umdid/prd_afestk/prd_estado
             * se heredan del padre (no se piden acá, ver withValidator: un
             * producto no puede ser variante Y tener variantes al mismo
             * tiempo).
             *
             * atb_id/prp_id son OBLIGATORIOS por fila -- lo que hace que un
             * producto sea "una variante" es justamente diferenciarse por
             * un atributo (RAM, color, etc.), así que no tiene sentido
             * dejarlo optativo. Cada fila elige su PROPIO atributo (no uno
             * solo para todo el lote): así un mismo lote puede tener una
             * variante que se diferencia por RAM y otra por Color sin que
             * se pisen entre sí. Categoría NO se pide acá -- la Publicación
             * Web nace en borrador sin categoría, y se completa cuando el
             * admin entra a esa pantalla (ver migración
             * make_pub_catid_nullable_in_publicacion_web_table).
             */
            'variantes' => ['nullable', 'array'],
            'variantes.*.prd_descri' => ['required_with:variantes', 'string', 'max:150'],
            'variantes.*.prd_codinv' => [
                'required_with:variantes', 'string', 'max:45', 'distinct',
                Rule::unique('producto', 'prd_codinv'),
            ],
            'variantes.*.prd_stkmin' => ['required_with:variantes', 'numeric', 'min:0'],
            'variantes.*.atb_id' => [
                'required_with:variantes', 'integer',
                Rule::exists('atributo', 'atb_id')->where(fn ($q) => $q->where('atb_tipo', 3)),
            ],
            'variantes.*.prp_id' => ['required_with:variantes', 'integer', 'exists:propiedad,prp_id'],
        ];
    }

    /**
     * La unidad alternativa de compra no puede ser igual a la unidad de
     * stock del producto (esa ya es la base, con factor 1 implícito).
     * Misma regla que ProductoEquivalenciaRequest, pero comparando contra
     * prd_umdid del propio payload (el producto todavía no existe en alta).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('tiene_equiv_compra')) {
                if ($this->filled('prd_umdid') && $this->filled('peq_umdid')
                    && (int) $this->prd_umdid === (int) $this->peq_umdid) {
                    $validator->errors()->add(
                        'peq_umdid',
                        'La unidad alternativa no puede ser la misma unidad de stock del producto.'
                    );
                }
            }

            // Un producto no puede ser variante de otro (prd_padre_id) Y,
            // al mismo tiempo, dar de alta variantes propias: rompería la
            // regla de jerarquía a un solo nivel (ver prd_padre_id arriba).
            if ($this->filled('prd_padre_id') && is_array($this->input('variantes')) && count($this->input('variantes')) > 0) {
                $validator->errors()->add(
                    'variantes',
                    'Un producto que es variante de otro no puede tener, a su vez, variantes propias.'
                );
            }

            // Cada prp_id elegido tiene que pertenecer de verdad al atb_id
            // de SU MISMA fila (evita, por error de frontend, mandar una
            // Propiedad de "Color" junto con un atb_id de "RAM").
            if (is_array($this->input('variantes'))) {
                foreach ($this->input('variantes') as $i => $fila) {
                    $atbId = $fila['atb_id'] ?? null;
                    $prpId = $fila['prp_id'] ?? null;
                    if ($atbId && $prpId && !\App\Models\Product\Propiedad::where('prp_id', $prpId)->where('prp_atbid', $atbId)->exists()) {
                        $validator->errors()->add(
                            "variantes.$i.prp_id",
                            'Esa propiedad no pertenece al atributo elegido en esta fila.'
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'prd_marid.required'  => 'Debe seleccionar la marca.',
            'prd_marid.exists'    => 'La marca seleccionada no existe.',
            'prd_tivid.required'  => 'Debe seleccionar el tipo de IVA.',
            'prd_tivid.exists'    => 'El tipo de IVA seleccionado no existe.',
            'prd_umdid.required'  => 'Debe seleccionar la unidad de medida.',
            'prd_umdid.exists'    => 'La unidad de medida seleccionada no existe.',
            'prd_codinv.required' => 'El código de inventario es obligatorio.',
            'prd_codinv.unique'   => 'Ya existe un producto con ese código de inventario.',
            'prd_descri.required' => 'El nombre del producto es obligatorio.',
            'prd_descri.max'      => 'El nombre no puede superar los 150 caracteres.',
            'prd_stkmin.required' => 'El stock mínimo es obligatorio.',
            'prd_stkmin.min'      => 'El stock mínimo no puede ser negativo.',
            'prd_padre_id.exists' => 'El producto seleccionado no puede usarse como modelo base (no existe o ya es, a su vez, una variante).',
            'prd_padre_id.not_in' => 'Un producto no puede ser variante de sí mismo.',
            'peq_umdid.required_if'  => 'Debe seleccionar la unidad alternativa.',
            'peq_umdid.exists'       => 'La unidad alternativa seleccionada no existe.',
            'peq_factor.required_if' => 'Debe indicar la equivalencia.',
            'peq_factor.min'         => 'La equivalencia debe ser mayor a 0.',
            'variantes.*.prd_descri.required_with' => 'Cada variante necesita un nombre.',
            'variantes.*.prd_codinv.required_with' => 'Cada variante necesita un código.',
            'variantes.*.prd_codinv.distinct' => 'Hay códigos repetidos entre las variantes que estás cargando.',
            'variantes.*.prd_codinv.unique'   => 'Ya existe un producto con ese código de inventario.',
            'variantes.*.prd_stkmin.required_with' => 'Cada variante necesita un stock mínimo.',
            'variantes.*.atb_id.required_with' => 'Elegí por qué atributo se diferencia esta variante.',
            'variantes.*.atb_id.exists' => 'El atributo elegido no existe o no es de tipo Seleccionable.',
            'variantes.*.prp_id.required_with' => 'Elegí el valor de ese atributo para esta variante.',
        ];
    }
}
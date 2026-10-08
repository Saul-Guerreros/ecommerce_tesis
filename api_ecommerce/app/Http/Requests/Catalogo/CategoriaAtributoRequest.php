<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Catalogo\CategoriaAtributo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoriaAtributoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * cta_catid solo se recibe al CREAR. En edición la fila ya pertenece a
     * una categoría y no puede moverse a otra: se toma la del registro
     * (ver categoriaId()), así nadie puede saltarse el UNIQUE mandando
     * otra categoría en el PUT.
     */
    public function rules(): array
    {
        $id = $this->route('categoria_atributo');

        return [
            'cta_catid'  => [
                Rule::requiredIf($this->isMethod('post')),
                'integer',
                'exists:categoria,cat_id',
            ],
            'cta_atbid'  => [
                'required',
                'integer',
                // Solo atributos activos y no eliminados (Atributo usa SoftDeletes).
                Rule::exists('atributo', 'atb_id')
                    ->where('atb_estado', 1)
                    ->whereNull('atb_eliminado'),
                Rule::unique('categoria_atributo', 'cta_atbid')
                    ->where('cta_catid', $this->categoriaId())
                    ->ignore($id, 'cta_id'),
            ],
            'cta_orden'  => ['nullable', 'integer', 'min:1', 'max:99'],
            'cta_obliga' => ['required', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Regla de negocio: la plantilla se define solo en subcategorías.
     * Las categorías raíz agrupan productos distintos (notebook y mouse
     * bajo la misma raíz), así que no tiene sentido una ficha común.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->isMethod('post') || $validator->errors()->has('cta_catid')) {
                return;
            }

            $esRaiz = DB::table('categoria')
                ->where('cat_id', $this->input('cta_catid'))
                ->whereNull('cat_idpadr')
                ->exists();

            if ($esRaiz) {
                $validator->errors()->add(
                    'cta_catid',
                    'La plantilla solo se puede definir en una subcategoría, no en una categoría principal.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'cta_catid.required'  => 'Debe seleccionar la subcategoría.',
            'cta_catid.exists'    => 'La subcategoría seleccionada no existe.',
            'cta_atbid.required'  => 'Debe seleccionar el atributo.',
            'cta_atbid.exists'    => 'El atributo no existe o está inactivo.',
            'cta_atbid.unique'    => 'Este atributo ya está en la plantilla de esta subcategoría.',
            'cta_orden.integer'   => 'El orden debe ser un número entero.',
            'cta_orden.min'       => 'El orden debe ser mayor o igual a 1.',
            'cta_orden.max'       => 'El orden no puede superar 99.',
            'cta_obliga.required' => 'Indique si el atributo es obligatorio.',
            'cta_obliga.in'       => 'El valor de obligatorio no es válido.',
        ];
    }

    /** Categoría contra la que se valida el UNIQUE (input en POST, registro en PUT). */
    private function categoriaId(): ?int
    {
        if ($this->isMethod('post')) {
            return (int) $this->input('cta_catid');
        }

        return CategoriaAtributo::whereKey($this->route('categoria_atributo'))->value('cta_catid');
    }
}

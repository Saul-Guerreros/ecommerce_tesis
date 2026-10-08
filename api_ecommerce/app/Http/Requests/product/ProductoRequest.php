<?php

namespace App\Http\Requests\product;

use App\Models\Producto;
use App\Models\Product\Atributo;
use App\Services\Catalogo\FichaTecnicaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el alta/edición de Producto (ProductoController@store / @update),
 * incluida el alta en lote de variantes (prd_padre_id) desde el mismo form.
 *
 * Las claves de error de variantes salen como "variantes.N.campo", que es
 * el formato que form-producto.component.ts mapea a cada fila.
 */
class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** ID del producto en edición (null al crear). Ruta: productos/{producto} */
    private function productoId(): ?int
    {
        $id = $this->route('producto') ?? $this->route('id');
        return $id ? (int) $id : null;
    }

    public function rules(): array
    {
        $id = $this->productoId();

        return [
            'prd_marid'  => ['required', 'integer', Rule::exists('marca', 'mar_id')],
            'prd_tivid'  => ['required', 'integer', Rule::exists('tipo_iva', 'tiv_id')],
            'prd_umdid'  => ['required', 'integer', Rule::exists('unidad_medida', 'umd_id')],
            'prd_codinv' => ['required', 'string', 'max:45',
                Rule::unique('producto', 'prd_codinv')->ignore($id, 'prd_id')],
            'prd_descri' => ['required', 'string', 'max:150'],
            'prd_stkmin' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'prd_afestk' => ['nullable', 'boolean'],
            'prd_estado' => ['nullable', 'boolean'],

            // Solo se acepta un padre que no sea, a su vez, variante.
            'prd_padre_id' => ['nullable', 'integer',
                Rule::exists('producto', 'prd_id')->where(fn ($q) => $q->whereNull('prd_padre_id'))],

            // Equivalencia inicial (solo se usa al crear).
            'tiene_equiv_compra' => ['nullable', 'boolean'],
            'peq_umdid'  => ['nullable', 'required_if:tiene_equiv_compra,true,1', 'integer',
                Rule::exists('unidad_medida', 'umd_id')],
            'peq_factor' => ['nullable', 'required_if:tiene_equiv_compra,true,1', 'numeric', 'gt:0'],

            // Alta en lote de variantes.
            'variantes'              => ['nullable', 'array'],
            'variantes.*.prd_descri' => ['required', 'string', 'max:150'],
            'variantes.*.prd_codinv' => ['required', 'string', 'max:45', 'distinct',
                Rule::unique('producto', 'prd_codinv'), 'different:prd_codinv'],
            'variantes.*.prd_stkmin' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            // Cualquier tipo de atributo activo. El valor llega en el campo
            // que corresponde a su tipo:
            //   1 Texto -> valor_texto | 2 Número -> valor_numero
            //   3 Seleccionable -> prp_id | 4 Múltiple -> propiedad_ids
            'variantes.*.atb_id'          => ['required', 'integer',
                Rule::exists('atributo', 'atb_id')->where('atb_estado', 1)->whereNull('atb_eliminado')],
            'variantes.*.valor_texto'     => ['nullable', 'string', 'max:150'],
            'variantes.*.valor_numero'    => ['nullable', 'numeric', 'min:0'],
            'variantes.*.prp_id'          => ['nullable', 'integer'],
            'variantes.*.propiedad_ids'   => ['nullable', 'array'],
            'variantes.*.propiedad_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $variantes = $this->input('variantes', []);
            if (empty($variantes) || ! is_array($variantes)) {
                return;
            }

            // Una variante no puede tener variantes propias.
            $id = $this->productoId();
            if ($id && Producto::whereKey($id)->whereNotNull('prd_padre_id')->exists()) {
                $validator->errors()->add('variantes', 'Este producto ya es una variante: no puede tener variantes propias.');
                return;
            }

            // Valor del diferenciador: mismas reglas que la Ficha Técnica
            // (obligatorio, rango numérico, propiedades del atributo, etc.).
            $ficha = app(FichaTecnicaService::class);
            $atributos = Atributo::with('propiedades')
                ->whereIn('atb_id', collect($variantes)->pluck('atb_id')->filter()->unique())
                ->get()
                ->keyBy('atb_id');

            // Campo del form donde se muestra el error, según el tipo.
            $campoPorTipo = [1 => 'valor_texto', 2 => 'valor_numero', 3 => 'prp_id', 4 => 'propiedad_ids'];

            foreach ($variantes as $i => $fila) {
                $atributo = $atributos->get((int) ($fila['atb_id'] ?? 0));
                if (! $atributo || $validator->errors()->has("variantes.$i.*")) {
                    continue; // ya tiene error de formato o el atributo no existe
                }

                if ($error = $ficha->validarDiferenciador($atributo, $ficha->valorDesdeFila($fila))) {
                    $campo = $campoPorTipo[(int) $atributo->atb_tipo] ?? 'atb_id';
                    $validator->errors()->add("variantes.$i.$campo", $error);
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'prd_codinv.unique'             => 'Ya existe un producto con ese código de inventario.',
            'prd_stkmin.decimal'            => 'El stock mínimo admite hasta 4 decimales.',
            'variantes.*.prd_stkmin.decimal' => 'El stock mínimo admite hasta 4 decimales.',
            'variantes.*.prd_codinv.unique' => 'Ese código ya está en uso por otro producto.',
            'variantes.*.prd_codinv.distinct' => 'Código repetido dentro del lote de variantes.',
            'variantes.*.prd_codinv.different' => 'La variante no puede usar el mismo código que el producto base.',
            'variantes.*.atb_id.exists'     => 'El atributo no existe o está inactivo.',
            'variantes.*.valor_numero.numeric' => 'Debe ser un número.',
            'variantes.*.valor_numero.min'  => 'No puede ser negativo.',
        ];
    }
}
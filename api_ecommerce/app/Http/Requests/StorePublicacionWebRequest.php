<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicacionWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta según tu lógica de permisos/roles
    }
	protected function prepareForValidation(): void
{
    $booleanos = ['featured_activo', 'trending_activo', 'selling_activo'];
 
    $normalizados = [];
    foreach ($booleanos as $campo) {
        if ($this->has($campo)) {
            $normalizados[$campo] = filter_var(
                $this->input($campo),
                FILTER_VALIDATE_BOOLEAN
            );
        }
    }
 
    $this->merge($normalizados);
}

    /**
     * No se puede PUBLICAR (pub_estado = 1) un producto sin precio de
     * venta: en la tienda aparecería a Gs 0. Un producto sin compras
     * confirmadas todavía no tiene precio calculado (ver PriceService).
     * En Borrador (0) o Archivado (2) sí se permite: el admin puede ir
     * armando la ficha mientras espera la primera compra.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((int) $this->input('pub_estado') !== 1) {
                return;
            }

            $producto = $this->route('producto');

            if ($producto && $producto->precioVitrina() === null) {
                $validator->errors()->add(
                    'pub_estado',
                    'El producto todavía no tiene precio de venta calculado. '
                    .'Registrá una compra o revisá sus modalidades en Gestión de Precios antes de publicarlo.'
                );
            }
        });
    }

    public function rules(): array
    {
        // La publicación viaja por el modelo binding de /productos/{producto}/publicacion,
        // así que la publicación actual (si ya existe) se obtiene por el producto.
        $publicacionActual = $this->route('producto')?->publicacionWeb;

        return [
            'pub_catid'  => ['required', 'integer', 'exists:categoria,cat_id'],
            'pub_titulo' => ['required', 'string', 'max:150'],
            // pub_prevta ya NO se acepta: el precio es dueño de `producto`
            // (Gestión de Precios). Guardar una copia acá la dejaba vieja
            // apenas PriceService recalculaba por una compra, y además
            // competía con el módulo de Campaña de Descuento (el descuento
            // se aplicaba ENCIMA de ese precio ya rebajado a mano).
            // opcional: si no se manda, el modelo lo autogenera desde pub_titulo.
            'pub_slug'   => [
                'nullable', 'string', 'max:180', 'alpha_dash',
                Rule::unique('publicacion_web', 'pub_slug')->ignore($publicacionActual?->pub_id, 'pub_id'),
            ],
            'pub_descwb' => ['nullable', 'string'],
            'pub_meta_titulo'      => ['nullable', 'string', 'max:160'],
            'pub_meta_descripcion' => ['nullable', 'string', 'max:320'],
            
            // 0 = Borrador, 1 = Publicado, 2 = Archivado
            'pub_estado' => ['required', 'integer', 'in:0,1,2'],
            // Override individual del umbral de "poco stock" (ver Configuración
            // de la Tienda). null = usa el general; mismos límites que ese.
            'pub_pocstk_umbral' => ['nullable', 'integer', 'min:1', 'max:1000'],

            // Destacar en el home del ecommerce (sección "Featured Products").
            // null/vacío = no destacado; número = posición en esa sección.
            'featured_activo' => ['nullable', 'boolean'],
			'featured_orden'  => ['nullable', 'integer', 'min:1', 'max:4', 'required_if:featured_activo,1'],
 
			'trending_activo' => ['nullable', 'boolean'],
			'trending_orden'  => ['nullable', 'integer', 'min:1', 'max:4', 'required_if:trending_activo,1'],
 
			'selling_activo' => ['nullable', 'boolean'],
			'selling_orden'  => ['nullable', 'integer', 'min:1', 'max:4', 'required_if:selling_activo,1'],

            // imágenes opcionales al crear la publicación
            'imagenes'   => ['sometimes', 'array'],
            'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'pub_catid.exists' => 'La categoría seleccionada no existe.',
            'pub_titulo.required' => 'El título para la web es obligatorio.',
            'pub_slug.alpha_dash' => 'La URL amigable solo puede tener letras, números, guiones y guiones bajos.',
            'pub_slug.unique' => 'Ya existe otra publicación con esa URL amigable.',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    public function rules(): array
    {
        // La publicación viaja por el modelo binding de /productos/{producto}/publicacion,
        // así que la publicación actual (si ya existe) se obtiene por el producto.
        $publicacionActual = $this->route('producto')?->publicacionWeb;

        return [
            'pub_catid'  => ['required', 'integer', 'exists:categoria,cat_id'],
            'pub_titulo' => ['required', 'string', 'max:150'],
            // precio propio de la ficha web: puede diferir del precio de
            // inventario (prd_prevta) para permitir ofertas o redondeo comercial.
            'pub_prevta' => ['nullable', 'numeric', 'min:0'],
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

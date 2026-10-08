<?php

namespace App\Http\Requests;

use App\Services\Catalogo\FichaTecnicaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
 
    // La ficha técnica viaja dentro del FormData como un string JSON
    // (FormData no maneja bien arreglos anidados). Se decodifica acá; si
    // el JSON es inválido queda como string y la regla 'array' lo rechaza.
    if (is_string($this->input('ficha'))) {
        $decodificada = json_decode($this->input('ficha'), true);
        $normalizados['ficha'] = is_array($decodificada) ? $decodificada : $this->input('ficha');
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

        $validator->after(fn (Validator $validator) => $this->validarCategoriaYFicha($validator));
    }

    /**
     * 1) La publicación va siempre en una SUBCATEGORÍA: la ficha técnica
     *    (y el buscador) dependen de la plantilla de la subcategoría.
     * 2) Ficha técnica: tipos y opciones válidas siempre; los atributos
     *    obligatorios solo se exigen al PUBLICAR (en Borrador se permite
     *    guardarla incompleta). Si el panel no manda 'ficha', se valida
     *    lo que ya está guardado, para que tampoco se pueda publicar
     *    "por fuera" con la ficha incompleta.
     *
     * Variantes (prd_padre_id): solo se validan sus atributos propios y,
     * al publicar, que el producto base tenga completos los obligatorios
     * (la variante los hereda, ver FichaTecnicaService).
     */
    private function validarCategoriaYFicha(Validator $validator): void
    {
        if ($validator->errors()->has('pub_catid') || $validator->errors()->has('ficha')) {
            return;
        }

        $categoriaId = (int) $this->input('pub_catid');

        $esRaiz = DB::table('categoria')
            ->where('cat_id', $categoriaId)
            ->whereNull('cat_idpadr')
            ->exists();

        if ($esRaiz) {
            $validator->errors()->add('pub_catid', 'Elegí una subcategoría (ej. Notebooks), no una categoría principal.');
            return;
        }

        $producto = $this->route('producto');
        if (! $producto) {
            return;
        }

        $servicio = app(FichaTecnicaService::class);
        $publicacion = $producto->publicacionWeb;
        $publicar = (int) $this->input('pub_estado') === 1;

        $ficha = $this->has('ficha')
            ? (array) $this->input('ficha', [])
            : ($publicacion ? $servicio->fichaGuardada($publicacion)->values()->all() : []);

        $errores = $producto->prd_padre_id
            ? $servicio->validarVariante($producto, $categoriaId, $ficha, $publicar)
            : $servicio->validar($categoriaId, $ficha, $publicar);

        foreach ($errores as $campo => $mensaje) {
            $validator->errors()->add($campo, $mensaje);
        }
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

            // Ficha técnica (ver FichaTecnicaService). Aquí solo la forma;
            // tipos, opciones y obligatorios se validan en withValidator().
            'ficha'                   => ['sometimes', 'nullable', 'array'],
            'ficha.*.atb_id'          => ['required', 'integer'],
            'ficha.*.valor_texto'     => ['nullable', 'string'],
            'ficha.*.valor_numero'    => ['nullable', 'numeric'],
            'ficha.*.propiedad_ids'   => ['nullable', 'array'],
            'ficha.*.propiedad_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'pub_catid.exists' => 'La categoría seleccionada no existe.',
            'pub_titulo.required' => 'El título para la web es obligatorio.',
            'pub_slug.alpha_dash' => 'La URL amigable solo puede tener letras, números, guiones y guiones bajos.',
            'pub_slug.unique' => 'Ya existe otra publicación con esa URL amigable.',
            'ficha.array' => 'La ficha técnica enviada no tiene un formato válido.',
            'ficha.*.valor_numero.numeric' => 'El valor debe ser un número.',
        ];
    }
}
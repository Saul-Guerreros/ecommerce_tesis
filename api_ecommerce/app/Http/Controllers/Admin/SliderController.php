<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Discount\Discount;
use App\Models\Marca;
use App\Models\PublicacionWeb;
use App\Models\Slider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SliderController extends Controller
{
    private const NOMBRES_DESTINO = [
        'producto'  => 'Producto',
        'categoria' => 'Categoría',
        'marca'     => 'Marca',
        'campania'  => 'Campaña',
        'busqueda'  => 'Búsqueda',
        'url'       => 'Enlace manual',
    ];

    /**
     * GET /admin/sliders-destinos?search=&producto_id=
     * Opciones para el bloque "Destino del banner" del formulario.
     */
    public function destinos(Request $request)
    {
        $search = trim((string) $request->search);

        $productos = PublicacionWeb::publicadas()
            ->when($search !== '', fn ($q) => $q->where('pub_titulo', 'ilike', '%' . $search . '%'))
            ->orderBy('pub_titulo')
            ->limit(50)
            ->get(['pub_prdid', 'pub_titulo', 'pub_slug']);

        // En edición, el producto ya elegido debe estar aunque no coincida con la búsqueda
        $actualId = (int) $request->producto_id;
        if ($actualId && !$productos->contains('pub_prdid', $actualId)) {
            $actual = PublicacionWeb::where('pub_prdid', $actualId)->first(['pub_prdid', 'pub_titulo', 'pub_slug']);
            if ($actual) {
                $productos->prepend($actual);
            }
        }

        $categorias = Categoria::get(['cat_id', 'cat_nombre', 'cat_idpadr']);
        $nombresCat = $categorias->pluck('cat_nombre', 'cat_id');

        $hoy = now()->toDateString();

        return response()->json([
            'productos' => $productos->map(fn ($p) => [
                'id'     => $p->pub_prdid,
                'nombre' => $p->pub_titulo,
                'slug'   => $p->pub_slug,
            ])->values(),

            'categorias' => $categorias->map(fn ($c) => [
                'id'     => $c->cat_id,
                'nombre' => $c->cat_idpadr
                    ? ($nombresCat[$c->cat_idpadr] ?? '') . ' › ' . $c->cat_nombre
                    : $c->cat_nombre,
            ])->sortBy('nombre')->values(),

            'marcas' => Marca::orderBy('mar_descri')->get(['mar_id', 'mar_descri'])
                ->map(fn ($m) => ['id' => $m->mar_id, 'nombre' => $m->mar_descri]),

            // Activas o programadas; las "Por link" se excluyen (son exclusivas)
            'campanias' => Discount::where('state', 1)
                ->where('type_campaing', '!=', 3)
                ->whereDate('end_date', '>=', $hoy)
                ->orderBy('start_date')
                ->get()
                ->map(fn ($d) => [
                    'id'     => $d->id,
                    'nombre' => $d->code . ' · -'
                        . ((int) $d->type_discount === 1
                            ? (float) $d->discount . '%'
                            : number_format((float) $d->discount, 0, ',', '.') . ' Gs.')
                        . ' · hasta ' . Carbon::parse($d->end_date)->format('d/m/Y'),
                ]),
        ]);
    }

    public function index(Request $request)
    {
        $search = $request->search;

        $sliders = Slider::where('sld_titulo', 'ilike', '%' . $search . '%')
            ->orderBy('sld_orden', 'asc')
            ->orderBy('sld_id', 'desc')
            ->paginate(25);

        return response()->json([
            'total'   => $sliders->total(),
            'sliders' => $sliders->map(fn ($slider) => $this->transform($slider)),
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validar($request, false);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['sld_titulo', 'sld_subtitulo', 'sld_label', 'sld_orden', 'sld_tipo', 'sld_posicion', 'sld_color']);
        $data = array_merge($data, $this->datosDestino($request));
        $data['sld_estado'] = $request->sld_estado ?? 1;

        if ((int) $request->sld_tipo !== 2) {
            $data['sld_posicion'] = null;
        }

        if ($request->hasFile('image')) {
            $data['sld_imagen'] = Storage::disk('public')->putFile('sliders', $request->file('image'));
        }

        $slider = Slider::create($data);

        return response()->json([
            'message' => 'Slider creado correctamente',
            'slider'  => $this->transform($slider),
        ], 201);
    }

    public function show(string $id)
    {
        $slider = Slider::find($id);

        if (!$slider) {
            return response()->json(['message' => 'Slider no encontrado'], 404);
        }

        return response()->json(['slider' => $this->transform($slider)]);
    }

    public function update(Request $request, string $id)
    {
        $slider = Slider::find($id);

        if (!$slider) {
            return response()->json(['message' => 'Slider no encontrado'], 404);
        }

        $validator = $this->validar($request, true);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['sld_titulo', 'sld_subtitulo', 'sld_label', 'sld_orden', 'sld_tipo', 'sld_posicion', 'sld_estado', 'sld_color']);
        $data = array_merge($data, $this->datosDestino($request));

        if ((int) $request->sld_tipo !== 2) {
            $data['sld_posicion'] = null;
        }

        if ($request->hasFile('image')) {
            if ($slider->sld_imagen) {
                Storage::disk('public')->delete($slider->sld_imagen);
            }
            $data['sld_imagen'] = Storage::disk('public')->putFile('sliders', $request->file('image'));
        }

        $slider->update($data);

        return response()->json([
            'message' => 'Slider actualizado correctamente',
            'slider'  => $this->transform($slider->fresh()),
        ], 200);
    }

    public function destroy(string $id)
    {
        $slider = Slider::find($id);

        if (!$slider) {
            return response()->json(['message' => 'Slider no encontrado'], 404);
        }

        $slider->delete();

        return response()->json(['message' => 'Slider eliminado correctamente'], 200);
    }

    // ---------------------------------------------------------------

    private function validar(Request $request, bool $esEdicion)
    {
        $validator = Validator::make($request->all(), [
            'sld_titulo'        => 'required|string|max:150',
            'image'             => [$esEdicion ? 'nullable' : 'required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:4096'],
            'sld_orden'         => 'nullable|integer',
            'sld_tipo'          => 'nullable|integer|in:1,2,3',
            'sld_posicion'      => 'nullable|integer|in:1,2|required_if:sld_tipo,2',
            'sld_estado'        => 'nullable|integer|in:1,2',
            'sld_destino_tipo'  => 'nullable|in:' . implode(',', Slider::DESTINOS),
            'sld_destino_id'    => 'nullable|integer|required_if:sld_destino_tipo,' . implode(',', Slider::DESTINOS_CON_ID),
            'sld_destino_valor' => 'nullable|string|max:255|required_if:sld_destino_tipo,' . implode(',', Slider::DESTINOS_CON_VALOR),
        ], [
            'sld_titulo.required'        => 'El título es obligatorio.',
            'image.required'             => 'Debe subir una imagen.',
            'sld_posicion.required_if'   => 'Elija si el banner secundario va a la izquierda o a la derecha.',
            'sld_destino_id.required_if' => 'Seleccione a dónde lleva el banner.',
            'sld_destino_valor.required_if' => 'Complete el destino del banner.',
        ]);

        // El destino debe existir y estar disponible según su tipo
        $validator->after(function ($v) use ($request) {
            $tipo = $request->sld_destino_tipo;
            $id   = (int) $request->sld_destino_id;

            if (in_array($tipo, Slider::DESTINOS_CON_ID, true) && $id) {
                $existe = match ($tipo) {
                    'producto'  => PublicacionWeb::publicadas()->where('pub_prdid', $id)->exists(),
                    'categoria' => Categoria::where('cat_id', $id)->exists(),
                    'marca'     => Marca::where('mar_id', $id)->exists(),
                    'campania'  => Discount::where('id', $id)->where('state', 1)->where('type_campaing', '!=', 3)
                        ->whereDate('end_date', '>=', now()->toDateString())->exists(),
                };
                if (!$existe) {
                    $v->errors()->add('sld_destino_id', 'El destino seleccionado no existe o ya no está disponible.');
                }
            }

            if ($tipo === 'url' && !preg_match('/^(\/\S*|https?:\/\/\S+)$/', (string) $request->sld_destino_valor)) {
                $v->errors()->add('sld_destino_valor', 'El enlace debe empezar con "/" (ruta de la tienda) o con https://');
            }
        });

        return $validator;
    }

    /** Normaliza el destino: según el tipo se guarda id o valor, nunca restos del tipo anterior. */
    private function datosDestino(Request $request): array
    {
        $tipo = $request->sld_destino_tipo ?: null;

        return [
            'sld_destino_tipo'  => $tipo,
            'sld_destino_id'    => in_array($tipo, Slider::DESTINOS_CON_ID, true) ? (int) $request->sld_destino_id : null,
            'sld_destino_valor' => in_array($tipo, Slider::DESTINOS_CON_VALOR, true) ? trim((string) $request->sld_destino_valor) : null,
        ];
    }

    private function descripcionDestino(Slider $slider): string
    {
        $tipo = $slider->sld_destino_tipo;
        if (!$tipo) {
            return 'Sin enlace';
        }

        $id = $slider->sld_destino_id;
        $nombre = match ($tipo) {
            'producto'  => PublicacionWeb::where('pub_prdid', $id)->value('pub_titulo'),
            'categoria' => Categoria::where('cat_id', $id)->value('cat_nombre'),
            'marca'     => Marca::where('mar_id', $id)->value('mar_descri'),
            'campania'  => Discount::where('id', $id)->value('code'),
            default     => $slider->sld_destino_valor,
        };

        return self::NOMBRES_DESTINO[$tipo] . ': ' . ($nombre ?? '(no disponible)');
    }

    private function transform(Slider $slider): array
    {
        return [
            'sld_id'              => $slider->sld_id,
            'sld_titulo'          => $slider->sld_titulo,
            'sld_subtitulo'       => $slider->sld_subtitulo,
            'sld_label'           => $slider->sld_label,
            'sld_destino_tipo'    => $slider->sld_destino_tipo,
            'sld_destino_id'      => $slider->sld_destino_id,
            'sld_destino_valor'   => $slider->sld_destino_valor,
            'destino_descripcion' => $this->descripcionDestino($slider),
            'sld_orden'           => $slider->sld_orden,
            'sld_tipo'            => $slider->sld_tipo,
            'sld_posicion'        => $slider->sld_posicion,
            'sld_estado'          => $slider->sld_estado,
            'sld_color'           => $slider->sld_color,
            'sld_creado'          => $slider->sld_creado,
            'sld_imagen'          => $slider->sld_imagen
                ? rtrim(config('app.url'), '/') . '/storage/' . $slider->sld_imagen
                : null,
        ];
    }
}
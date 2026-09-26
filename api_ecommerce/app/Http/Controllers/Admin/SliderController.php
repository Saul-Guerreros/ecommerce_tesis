<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SliderController extends Controller
{

    public function productos(Request $request)
    {
        // prd_titulo se movió a publicacion_web.pub_titulo (ver migración
        // de Publicación Web); se busca/ordena a través de esa relación.
        $search = $request->search;

        $productos = Producto::with('publicacionWeb')
            ->when($request->filled('search'), function ($q) use ($search) {
                $q->where('prd_codinv', 'like', '%' . $search . '%')
                    ->orWhereHas('publicacionWeb', fn ($p) => $p->where('pub_titulo', 'like', '%' . $search . '%'));
            })
            ->orderBy('prd_codinv')
            ->limit(50)
            ->get(['prd_id', 'prd_codinv'])
            ->map(fn ($p) => [
                'prd_id'     => $p->prd_id,
                'prd_codinv' => $p->prd_codinv,
                'prd_titulo' => $p->publicacionWeb?->pub_titulo ?? $p->prd_codinv,
            ]);

        return response()->json(['productos' => $productos]);
    }

   
    public function productoShow(string $id)
    {
        $producto = Producto::with('publicacionWeb')->findOrFail($id);

        return response()->json([
            'producto' => [
                'prd_id'     => $producto->prd_id,
                'prd_codinv' => $producto->prd_codinv,
                'prd_titulo' => $producto->publicacionWeb?->pub_titulo ?? $producto->prd_codinv,
            ],
        ]);
    }

   
    public function index(Request $request)
    {
        $search = $request->search;

        $sliders = Slider::where('sld_titulo', 'like', '%' . $search . '%')
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
        $validator = Validator::make($request->all(), [
            'sld_prdid'  => 'required|integer|exists:producto,prd_id',
            'sld_titulo' => 'required|string|max:150',
            'image'      => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'sld_enlace' => 'nullable|string|max:255',
            'sld_orden'  => 'nullable|integer',
            'sld_tipo'   => 'nullable|integer|in:1,2,3',
            'sld_posicion' => 'nullable|integer|in:1,2|required_if:sld_tipo,2',
            'sld_estado' => 'nullable|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'sld_prdid', 'sld_titulo', 'sld_subtitulo', 'sld_label',
            'sld_enlace', 'sld_orden', 'sld_tipo', 'sld_posicion', 'sld_color',
        ]);

        $data['sld_estado'] = $request->sld_estado ?? 1;

        if ($request->hasFile('image')) {
            $data['sld_imagen'] = Storage::disk('public')->putFile('sliders', $request->file('image'));
        }

        $slider = Slider::create($data);

        return response()->json([
            'message' => 200,
            'slider'  => $this->transform($slider),
        ]);
    }

   
    public function show(string $id)
    {
        $slider = Slider::findOrFail($id);

        return response()->json(['slider' => $this->transform($slider)]);
    }

    
    public function update(Request $request, string $id)
    {
        $slider = Slider::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'sld_prdid'  => 'required|integer|exists:producto,prd_id',
            'sld_titulo' => 'required|string|max:150',
            'image'      => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
			'sld_tipo'   => 'nullable|integer|in:1,2,3',
            'sld_posicion' => 'nullable|integer|in:1,2|required_if:sld_tipo,2',
            'sld_estado' => 'nullable|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'sld_prdid', 'sld_titulo', 'sld_subtitulo', 'sld_label',
            'sld_enlace', 'sld_orden', 'sld_tipo', 'sld_posicion', 'sld_estado', 'sld_color',
        ]);

        if ($request->hasFile('image')) {
            if ($slider->sld_imagen) {
                Storage::disk('public')->delete($slider->sld_imagen);
            }
            $data['sld_imagen'] = Storage::disk('public')->putFile('sliders', $request->file('image'));
        }

        $slider->update($data);

        return response()->json([
            'message' => 200,
            'slider'  => $this->transform($slider),
        ]);
    }

    
    public function destroy(string $id)
    {
        $slider = Slider::findOrFail($id);
        $slider->delete();

        return response()->json(['message' => 200]);
    }

   
    private function transform(Slider $slider): array
    {
        return [
            'sld_id'        => $slider->sld_id,
            'sld_prdid'     => $slider->sld_prdid,
            'sld_titulo'    => $slider->sld_titulo,
            'sld_subtitulo' => $slider->sld_subtitulo,
            'sld_label'     => $slider->sld_label,
            'sld_enlace'    => $slider->sld_enlace,
            'sld_orden'     => $slider->sld_orden,
            'sld_tipo'      => $slider->sld_tipo,
            'sld_posicion'  => $slider->sld_posicion,
            'sld_estado'    => $slider->sld_estado,
            'sld_color'     => $slider->sld_color,
            'sld_creado'    => $slider->sld_creado,
            'sld_imagen'    => $slider->sld_imagen
                ? rtrim(env('APP_URL'), '/') . '/storage/' . $slider->sld_imagen
                : null,
        ];
    }
}

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
        $search = $request->search;

        $productos = Producto::when($request->filled('search'), function ($q) use ($search) {
                $q->where('prd_titulo', 'like', '%' . $search . '%');
            })
            ->orderBy('prd_titulo')
            ->limit(50)
            ->get(['prd_id', 'prd_titulo', 'prd_codinv']);

        return response()->json(['productos' => $productos]);
    }

   
    public function productoShow(string $id)
    {
        $producto = Producto::findOrFail($id);

        return response()->json([
            'producto' => $producto->only(['prd_id', 'prd_titulo', 'prd_codinv']),
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
            'sld_tipo'   => 'nullable|integer',
            'sld_estado' => 'nullable|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'sld_prdid', 'sld_titulo', 'sld_subtitulo', 'sld_label',
            'sld_enlace', 'sld_orden', 'sld_tipo', 'sld_color',
        ]);

        $data['sld_estado'] = $request->sld_estado ?? 1;

        if ($request->hasFile('image')) {
            $data['sld_imagen'] = Storage::putFile('sliders', $request->file('image'));
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
            'sld_estado' => 'nullable|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'sld_prdid', 'sld_titulo', 'sld_subtitulo', 'sld_label',
            'sld_enlace', 'sld_orden', 'sld_tipo', 'sld_estado', 'sld_color',
        ]);

        if ($request->hasFile('image')) {
            if ($slider->sld_imagen) {
                Storage::delete($slider->sld_imagen);
            }
            $data['sld_imagen'] = Storage::putFile('sliders', $request->file('image'));
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
            'sld_estado'    => $slider->sld_estado,
            'sld_color'     => $slider->sld_color,
            'sld_creado'    => $slider->sld_creado,
            'sld_imagen'    => $slider->sld_imagen
                ? rtrim(env('APP_URL'), '/') . '/storage/' . $slider->sld_imagen
                : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Resources\product\CategorieResource;
use App\Models\product\categorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class categorieController extends Controller
{
    // GET /api/categorias  (usado por el <select> del formulario de producto Y por el listado del CRUD)
    public function index(): JsonResponse
    {
        $categorias = categorie::orderBy('cat_nombre')
            ->get(['cat_id', 'cat_nombre', 'cat_idpadr', 'cat_nivel']);

        return response()->json(['data' => $categorias]);
    }

    // GET /api/categorias/{id}
    public function show($id)
    {
        $categoria = categorie::with('padre')->find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json(new CategorieResource($categoria), 200);
    }

    // POST /api/categorias
    public function store(Request $request)
    {
       $validator = Validator::make($request->all(), [
            'cat_nombre' => 'required|string|max:45',
            'cat_idpadr' => 'nullable|integer|exists:categoria,cat_id',
            'cat_icono' => ['nullable', 'string', Rule::in([
                'computadora', 'notebook', 'perifericos', 'componentes', 'redes',
                'almacenamiento', 'impresion', 'accesorios', 'audio', 'gaming',
                'celulares', 'camara', 'promociones', 'general',
            ])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $nivel = 1;
        if ($request->cat_idpadr) {
            $padre = categorie::find($request->cat_idpadr);
            $nivel = $padre->cat_nivel + 1;
        }

        $categoria = categorie::create([
            'cat_nombre' => $request->cat_nombre,
             'cat_idpadr' => $request->cat_idpadr,
             'cat_nivel'  => $nivel,
             'cat_icono' => ['nullable', 'string', Rule::in([
                    'computadora', 'notebook', 'perifericos', 'componentes', 'redes',
                    'almacenamiento', 'impresion', 'accesorios', 'audio', 'gaming',
                    'celulares', 'camara', 'promociones', 'general',
                ])],
        ]);

        return response()->json(new CategorieResource($categoria), 201);
    }

    // PUT /api/categorias/{id}
    public function update(Request $request, $id)
    {
        $categoria = categorie::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'cat_nombre' => 'required|string|max:45',
            'cat_idpadr' => 'nullable|integer|exists:categoria,cat_id',
            'cat_icono' => ['nullable', 'string', Rule::in([
                'computadora', 'notebook', 'perifericos', 'componentes', 'redes',
                'almacenamiento', 'impresion', 'accesorios', 'audio', 'gaming',
                'celulares', 'camara', 'promociones', 'general',
            ])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->cat_idpadr == $id) {
            return response()->json(['message' => 'Una categoría no puede ser su propia subcategoría'], 422);
        }

        $nivel = 1;
        if ($request->cat_idpadr) {
            $padre = categorie::find($request->cat_idpadr);
            $nivel = $padre->cat_nivel + 1;
        }

       $categoria->update([
        'cat_nombre' => $request->cat_nombre,
        'cat_idpadr' => $request->cat_idpadr,
         'cat_nivel'  => $nivel,
            'cat_icono' => ['nullable', 'string', Rule::in([
                'computadora', 'notebook', 'perifericos', 'componentes', 'redes',
                'almacenamiento', 'impresion', 'accesorios', 'audio', 'gaming',
                'celulares', 'camara', 'promociones', 'general',
            ])],
        ]);

        return response()->json(new CategorieResource($categoria), 200);
    }

    // DELETE /api/categorias/{id}
    public function destroy($id)
    {
        $categoria = categorie::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        if ($categoria->hijos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar: tiene subcategorías asociadas'], 422);
        }
        
        if ($categoria->cat_imagen) {
            Storage::disk('public')->delete($categoria->cat_imagen);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente'], 200);
    }

        // POST /api/categorias/{id}/imagen   (multipart/form-data, campo: imagen)
    public function subirImagen(Request $request, $id): JsonResponse
    {
        $categoria = categorie::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        if ($categoria->cat_idpadr) {
            return response()->json(['message' => 'Solo las categorías principales llevan imagen'], 422);
        }

        $validator = Validator::make($request->all(), [
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Reemplazo: se borra el archivo anterior para no dejar basura en el disco
        if ($categoria->cat_imagen) {
            Storage::disk('public')->delete($categoria->cat_imagen);
        }

        $categoria->cat_imagen = Storage::disk('public')->putFile('categorias', $request->file('imagen'));
        $categoria->save();

        return response()->json(new CategorieResource($categoria), 200);
    }

    // DELETE /api/categorias/{id}/imagen
    public function eliminarImagen($id): JsonResponse
    {
        $categoria = categorie::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        if ($categoria->cat_imagen) {
            Storage::disk('public')->delete($categoria->cat_imagen);
            $categoria->cat_imagen = null;
            $categoria->save();
        }

        return response()->json(new CategorieResource($categoria), 200);
    }
}
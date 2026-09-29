<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Resources\product\CategorieResource;
use App\Models\product\categorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'cat_icono'  => 'nullable|string|max:50', // NUEVO
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
             'cat_icono'  => $request->cat_icono, // NUEVO
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
             'cat_icono'  => 'nullable|string|max:50', // NUEVO
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
        'cat_icono'  => $request->cat_icono, // NUEVO
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

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente'], 200);
    }
}
<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Resources\product\MarcaResource;
use App\Models\product\marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class marcaController extends Controller
{
    // GET /api/marcas  (usado por el <select> del formulario de producto Y por el listado del CRUD)
    public function index(): JsonResponse
    {
        $marcas = marca::orderBy('mar_descri')->get(['mar_id', 'mar_descri']);
        return response()->json(['data' => $marcas]);
    }

    // GET /api/marcas/{id}
    public function show($id)
    {
        $marca = marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        return response()->json(new MarcaResource($marca), 200);
    }

    // POST /api/marcas
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mar_descri' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $marca = marca::create([
            'mar_descri' => $request->mar_descri,
        ]);

        return response()->json(new MarcaResource($marca), 201);
    }

    // PUT /api/marcas/{id}
    public function update(Request $request, $id)
    {
        $marca = marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mar_descri' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $marca->update([
            'mar_descri' => $request->mar_descri,
        ]);

        return response()->json(new MarcaResource($marca), 200);
    }

    // DELETE /api/marcas/{id}
    public function destroy($id)
    {
        $marca = marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $marca->delete();

        return response()->json(['message' => 'Marca eliminada correctamente'], 200);
    }
}
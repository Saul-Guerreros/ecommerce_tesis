<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    // GET /api/categorias  (para llenar el <select> del formulario de producto)
    public function index(): JsonResponse
    {
        $categorias = Categoria::orderBy('cat_nombre')
            ->get(['cat_id', 'cat_nombre', 'cat_idpadr', 'cat_nivel']);

        return response()->json(['data' => $categorias]);
    }
}
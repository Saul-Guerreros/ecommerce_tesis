<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;

class MarcaController extends Controller
{
    // GET /api/marcas  (para llenar el <select> del formulario de producto)
    public function index(): JsonResponse
    {
        $marcas = Marca::orderBy('mar_descri')->get(['mar_id', 'mar_descri']);

        return response()->json(['data' => $marcas]);
    }
}

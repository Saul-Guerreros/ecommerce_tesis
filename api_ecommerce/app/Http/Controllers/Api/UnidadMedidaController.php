<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;

class UnidadMedidaController extends Controller
{
    // GET /api/unidades-medida (para llenar el <select> del formulario de producto)
    public function index(): JsonResponse
    {
        $unidades = UnidadMedida::where('umd_estado', 1)
            ->orderBy('umd_nombre')
            ->get(['umd_id', 'umd_codigo', 'umd_nombre']);

        return response()->json(['data' => $unidades]);
    }
}

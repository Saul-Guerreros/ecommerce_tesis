<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoIva;
use Illuminate\Http\JsonResponse;

class TipoIvaController extends Controller
{
    // GET /api/tipos-iva (para llenar el <select> del formulario de producto)
    public function index(): JsonResponse
    {
        $tipos = TipoIva::where('tiv_estado', 1)
            ->orderBy('tiv_tasa')
            ->get(['tiv_id', 'tiv_descri', 'tiv_tasa']);

        return response()->json(['data' => $tipos]);
    }
}

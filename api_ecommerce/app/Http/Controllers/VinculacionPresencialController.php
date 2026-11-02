<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\VincularCuentaRequest;
use App\Services\Cliente\VinculacionPresencialService;
use Illuminate\Http\JsonResponse;

/** Endpoint público de la tienda: crea la cuenta web sobre una ficha cargada en el mostrador. */
class VinculacionPresencialController extends Controller
{
    public function __construct(private VinculacionPresencialService $vinculacion) {}

    // POST /api/auth/vincular_cuenta
    public function vincular(VincularCuentaRequest $request): JsonResponse
    {
        $d = $request->validated();

        $this->vinculacion->vincular($d['tipo_documento'], $d['cedula'], $d['codigo'], $d['email'], $d['password']);

        return response()->json(['message' => 'Cuenta creada. Te enviamos un correo para activarla.'], 201);
    }
}
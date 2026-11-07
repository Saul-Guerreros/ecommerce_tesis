<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\DatosContactoRequest;
use App\Http\Resources\Configuracion\DatosContactoResource;
use App\Models\Configuracion\DatosContacto;
use Illuminate\Http\JsonResponse;

/** Datos de contacto de la tienda (singleton). */
class DatosContactoController extends Controller
{
    // GET /api/admin/datos-contacto
    public function show(): JsonResponse
    {
        return response()->json(['data' => new DatosContactoResource(DatosContacto::actual())]);
    }

    // PUT /api/admin/datos-contacto
    public function update(DatosContactoRequest $request): JsonResponse
    {
        $contacto = DatosContacto::actual();
        $contacto->update($request->validated() + ['dco_usuid' => auth('api')->id()]);

        return response()->json([
            'message' => 'Datos de contacto actualizados.',
            'data'    => new DatosContactoResource($contacto->fresh()),
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicacionWeb;
use App\Services\Comportamiento\ActividadClienteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Actividad pública de la tienda (invitado o cliente con JWT).
 * Va separada de VitrinaController::detalle() porque el chatbot también
 * consulta ese detalle y no debe contarse como una vista del cliente.
 */
class ActividadController extends Controller
{
    public function __construct(private readonly ActividadClienteService $actividad)
    {
    }

    /** POST /api/tienda/actividad/vista { slug, bus? } → 204 */
    public function vista(Request $request): Response
    {
        $datos = $request->validate([
            'slug' => ['required', 'string', 'max:255'],
            'bus'  => ['nullable', 'integer', 'min:1'],
        ]);

        $productoId = PublicacionWeb::publicadas()
            ->where('pub_slug', $datos['slug'])
            ->value('pub_prdid');

        if ($productoId) {
            $this->actividad->registrarVista($request, (int) $productoId, $datos['bus'] ?? null);
        }

        return response()->noContent();
    }
}
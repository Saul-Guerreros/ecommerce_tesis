<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\FaqTienda;
use Illuminate\Http\JsonResponse;

class FaqPublicoController extends Controller
{
    // GET /api/tienda/faq
    public function index(): JsonResponse
    {
        $faqs = FaqTienda::activas()->get(['fqt_tema', 'fqt_pregunta', 'fqt_respuesta']);

        $agrupado = $faqs->groupBy('fqt_tema')->map(function ($items, $tema) {
            return [
                'tema'      => $tema,
                'etiqueta'  => FaqTienda::TEMAS[$tema] ?? $tema,
                'preguntas' => $items->map(fn ($f) => [
                    'pregunta'  => $f->fqt_pregunta,
                    'respuesta' => $f->fqt_respuesta,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $agrupado]);
    }
}
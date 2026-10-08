<?php

namespace App\Http\Controllers\Admin\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\FaqTiendaRequest;
use App\Models\Chat\FaqTienda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MÓDULO 6 - ChatBot: información de la tienda que usa el asistente.
 * Cada cambio invalida la caché de instrucciones (FaqTienda::booted),
 * así el bot usa el dato nuevo desde el siguiente mensaje.
 */
class FaqTiendaController extends Controller
{
    // GET /api/admin/faq-tienda/config
    public function config(): JsonResponse
    {
        $temas = collect(FaqTienda::TEMAS)
            ->map(fn (string $etiqueta, string $valor) => ['valor' => $valor, 'etiqueta' => $etiqueta])
            ->values();

        return response()->json(['data' => ['temas' => $temas]]);
    }

    // GET /api/admin/faq-tienda?search=&tema=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = FaqTienda::query();

        if ($request->filled('search')) {
            $search = '%' . mb_strtolower($request->search) . '%';
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(fqt_pregunta) LIKE ?', [$search])
                ->orWhereRaw('LOWER(fqt_respuesta) LIKE ?', [$search]));
        }

        if ($request->filled('tema')) {
            $query->where('fqt_tema', $request->tema);
        }

        if ($request->filled('estado')) {
            $query->where('fqt_activo', $request->boolean('estado'));
        }

        $faqs = $query->orderBy('fqt_tema')->orderBy('fqt_orden')->orderBy('fqt_id')->get();

        // Todo lo activo viaja en CADA mensaje del chat: el admin ve cuánto pesa.
        $caracteres = (int) FaqTienda::where('fqt_activo', true)
            ->selectRaw('COALESCE(SUM(LENGTH(fqt_pregunta) + LENGTH(fqt_respuesta)), 0) AS total')
            ->value('total');

        return response()->json(['data' => $faqs, 'meta' => ['caracteres_activos' => $caracteres]]);
    }

    public function store(FaqTiendaRequest $request): JsonResponse
    {
        $faq = FaqTienda::create([
            'fqt_orden' => 0,
            'fqt_activo' => true,
            ...$request->validated(),
        ]);

        return response()->json(['message' => 'Información registrada correctamente', 'data' => $faq], 201);
    }

    public function update(FaqTiendaRequest $request, int $id): JsonResponse
    {
        $faq = FaqTienda::find($id);
        if (! $faq) {
            return response()->json(['message' => 'Información no encontrada'], 404);
        }

        $faq->update($request->validated());

        return response()->json(['message' => 'Información actualizada correctamente', 'data' => $faq->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $faq = FaqTienda::find($id);
        if (! $faq) {
            return response()->json(['message' => 'Información no encontrada'], 404);
        }

        $faq->delete();

        return response()->json(['message' => 'Información eliminada correctamente']);
    }

    // PATCH /api/admin/faq-tienda/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $faq = FaqTienda::find($id);
        if (! $faq) {
            return response()->json(['message' => 'Información no encontrada'], 404);
        }

        $faq->update(['fqt_activo' => ! $faq->fqt_activo]);

        return response()->json([
            'message' => $faq->fqt_activo ? 'El asistente vuelve a usar esta información' : 'El asistente ya no usa esta información',
        ]);
    }
}
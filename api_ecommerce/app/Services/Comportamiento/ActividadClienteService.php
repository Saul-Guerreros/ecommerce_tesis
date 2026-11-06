<?php

namespace App\Services\Comportamiento;

use App\Models\Comportamiento\Busqueda;
use App\Models\Comportamiento\VistaProducto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registra la actividad de los clientes en la tienda (búsquedas y vistas
 * de producto) para el Análisis de comportamiento.
 *
 * Reglas:
 *  - Nunca rompe la operación del cliente: ante un error devuelve null y deja log.
 *  - No registra al personal (rol distinto de Cliente): sus pruebas no ensucian métricas.
 *  - Deduplica la misma acción del mismo visitante dentro de VENTANA_MINUTOS.
 */
class ActividadClienteService
{
    public const HEADER_VISITANTE = 'X-Visitante';
    private const VENTANA_MINUTOS = 30;
    private const ROL_CLIENTE = 'Cliente';

    /** @return int|null id de la búsqueda (nueva o la deduplicada) */
    public function registrarBusqueda(Request $request, string $termino, string $modo, int $total, string $origen): ?int
    {
        try {
            [$usuarioId, $visita] = $this->identificar($request);

            if ($usuarioId === false) {
                return null; // personal de la tienda
            }

            $normal = $this->normalizar($termino);

            if ($normal === '') {
                return null;
            }

            $existente = $this->recienteDe(Busqueda::query(), 'bus', $usuarioId, $visita)
                ?->where('bus_termnorm', $normal)
                ->where('bus_origen', $origen)
                ->value('bus_id');

            if ($existente) {
                return (int) $existente;
            }

            return Busqueda::create([
                'bus_termino'  => Str::limit(trim($termino), 147, '...'),
                'bus_termnorm' => $normal,
                'bus_total'    => max(0, $total),
                'bus_modo'     => $modo,
                'bus_origen'   => $origen,
                'bus_usuid'    => $usuarioId,
                'bus_visita'   => $visita,
            ])->bus_id;
        } catch (Throwable $e) {
            Log::warning('Actividad: no se pudo registrar la búsqueda', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function registrarVista(Request $request, int $productoId, ?int $busquedaId): void
    {
        try {
            [$usuarioId, $visita] = $this->identificar($request);

            if ($usuarioId === false) {
                return;
            }

            $repetida = $this->recienteDe(VistaProducto::query(), 'vpr', $usuarioId, $visita)
                ?->where('vpr_prdid', $productoId)
                ->exists();

            if ($repetida) {
                return;
            }

            // Solo se acepta una búsqueda real y reciente (evita ids inventados).
            if ($busquedaId && ! Busqueda::whereKey($busquedaId)
                    ->where('bus_creado', '>=', now()->subHours(2))->exists()) {
                $busquedaId = null;
            }

            VistaProducto::create([
                'vpr_prdid'  => $productoId,
                'vpr_busid'  => $busquedaId,
                'vpr_usuid'  => $usuarioId,
                'vpr_visita' => $visita,
            ]);
        } catch (Throwable $e) {
            Log::warning('Actividad: no se pudo registrar la vista', ['error' => $e->getMessage()]);
        }
    }

    /** Texto comparable: "Notebook  HP" y "notebook hp" cuentan como el mismo término. */
    public function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->limit(150, '')->value();
    }

    /**
     * @return array{0: int|null|false, 1: string|null}
     *         usuarioId: int (cliente), null (invitado) o false (personal: no registrar)
     */
    private function identificar(Request $request): array
    {
        $visita = $request->header(self::HEADER_VISITANTE);
        $visita = is_string($visita) && Str::isUuid($visita) ? strtolower($visita) : null;

        /** @var Usuario|null $usuario */
        $usuario = auth('api')->user();

        if (! $usuario) {
            return [null, $visita];
        }

        if ($usuario->rol?->rol_descri !== self::ROL_CLIENTE) {
            return [false, $visita];
        }

        return [(int) $usuario->usu_id, $visita];
    }

    /**
     * Acciones del mismo visitante en la ventana de deduplicación.
     * Sin ningún identificador no se deduplica (devuelve null).
     */
    private function recienteDe($query, string $prefijo, ?int $usuarioId, ?string $visita)
    {
        if (! $usuarioId && ! $visita) {
            return null;
        }

        return $query
            ->where("{$prefijo}_creado", '>=', now()->subMinutes(self::VENTANA_MINUTOS))
            ->where(function ($q) use ($prefijo, $usuarioId, $visita) {
                if ($usuarioId) {
                    $q->orWhere("{$prefijo}_usuid", $usuarioId);
                }
                if ($visita) {
                    $q->orWhere("{$prefijo}_visita", $visita);
                }
            });
    }
}
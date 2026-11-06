<?php

namespace App\Services\Comportamiento;

use App\Services\IA\GeminiClient;
use App\Services\Reporte\ComportamientoReporteService;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Support\Facades\Cache;

/**
 * Convierte el análisis de comportamiento en recomendaciones accionables
 * para el negocio, usando Gemini con salida JSON estructurada.
 *
 * Privacidad: a la IA solo viajan agregados o el perfil SIN nombre ni documento.
 * Costo: el resultado se cachea; solo se regenera si el usuario lo pide.
 */
class InsightsComportamientoService
{
    private const HORAS_CACHE = 12;
    private const MAX_HALLAZGOS = 6;
    private const PRIORIDADES = ['alta', 'media', 'baja'];
    private const AREAS = ['catalogo', 'stock', 'precios', 'marketing', 'ventas', 'experiencia'];

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly ComportamientoReporteService $reporte,
    ) {
    }

    public function disponible(): bool
    {
        return $this->gemini->disponible();
    }

    /** @return array{resumen: string, hallazgos: array, generado: string}|null */
    public function generar(FiltrosReporte $f, ?int $clienteId = null, bool $forzar = false): ?array
    {
        $clave = $f->claveCache('comportamiento:ia:' . ($clienteId ?? 'general'));

        if (! $forzar && ($guardado = Cache::get($clave))) {
            return $guardado + ['desde_cache' => true];
        }

        $datos = $clienteId
            ? $this->reporte->perfilAnonimo($clienteId, $f)
            : $this->reporte->resumenParaIa($f);

        $respuesta = $this->gemini->generarJson(
            $clienteId ? $this->instruccionesCliente() : $this->instruccionesGeneral(),
            json_encode($datos, JSON_UNESCAPED_UNICODE),
            $this->esquema(),
        );

        if (! $respuesta) {
            return null; // un fallo no se cachea
        }

        $resultado = $this->sanear($respuesta);
        Cache::put($clave, $resultado, now()->addHours(self::HORAS_CACHE));

        return $resultado + ['desde_cache' => false];
    }

    private function instruccionesGeneral(): string
    {
        return <<<'TXT'
Sos analista comercial de Z Informática, una tienda de informática de Capiatá (Paraguay) que vende en línea.
Recibís en JSON el análisis de comportamiento de sus clientes en un período: búsquedas, búsquedas sin
resultado, productos más buscados, horarios de compra, productos comprados juntos, recompra, contado/crédito,
envío/retiro, embudo de conversión, carritos abandonados y segmentos RFM.

Reglas:
1. Basate SOLO en los números recibidos. No inventes datos, productos ni porcentajes.
2. Cada hallazgo debe citar el dato que lo respalda y proponer UNA acción concreta y realizable por una tienda
   pequeña (3 empleados, una sucursal): traer stock, armar un combo, publicar en un horario, campaña de cupón, etc.
3. Priorizá por impacto en ventas: "alta" solo si hay evidencia clara y repetida.
4. Si los datos son escasos, decilo en el resumen y proponé qué medir; no fuerces conclusiones.
5. Español neutro, frases cortas, montos en guaraníes (Gs.).
TXT;
    }

    private function instruccionesCliente(): string
    {
        return <<<'TXT'
Sos asesor comercial de Z Informática (tienda de informática, Paraguay). Recibís en JSON el perfil de compra de
UN cliente (sin datos personales): segmento RFM, recompra, categorías y marcas preferidas, productos comprados,
rango de precios, hábitos de compra, búsquedas, productos vistos sin comprar, favoritos, carrito y productos
complementarios sugeridos.

Reglas:
1. Usá SOLO la información recibida. No inventes productos ni datos.
2. Proponé acciones de venta personalizadas y respetuosas para ESTE cliente (qué ofrecerle, cuándo y por qué
   medio), citando el dato que las justifica.
3. Si hay productos vistos sin comprar, favoritos no comprados o carrito abandonado, priorizalos.
4. Si el cliente tiene poca actividad, decilo en el resumen y sugerí una sola acción simple.
5. Español neutro, frases cortas, montos en guaraníes (Gs.).
TXT;
    }

    private function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resumen' => ['type' => 'string'],
                'hallazgos' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'titulo'    => ['type' => 'string'],
                            'evidencia' => ['type' => 'string'],
                            'accion'    => ['type' => 'string'],
                            'prioridad' => ['type' => 'string', 'enum' => self::PRIORIDADES],
                            'area'      => ['type' => 'string', 'enum' => self::AREAS],
                        ],
                        'required' => ['titulo', 'evidencia', 'accion', 'prioridad', 'area'],
                    ],
                ],
            ],
            'required' => ['resumen', 'hallazgos'],
        ];
    }

    /** Valida la respuesta del modelo: nunca se confía en ella tal cual llega. */
    private function sanear(array $r): array
    {
        $texto = fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);

        $hallazgos = collect($r['hallazgos'] ?? [])
            ->filter(fn ($h) => is_array($h) && ! empty($h['titulo']) && ! empty($h['accion']))
            ->take(self::MAX_HALLAZGOS)
            ->map(fn (array $h) => [
                'titulo'    => $texto($h['titulo'], 120),
                'evidencia' => $texto($h['evidencia'] ?? '', 300),
                'accion'    => $texto($h['accion'], 300),
                'prioridad' => in_array($h['prioridad'] ?? null, self::PRIORIDADES, true) ? $h['prioridad'] : 'media',
                'area'      => in_array($h['area'] ?? null, self::AREAS, true) ? $h['area'] : 'ventas',
            ])
            ->sortBy(fn ($h) => array_search($h['prioridad'], self::PRIORIDADES, true))
            ->values()
            ->all();

        return [
            'resumen'   => $texto($r['resumen'] ?? '', 600),
            'hallazgos' => $hallazgos,
            'generado'  => now()->toDateTimeString(),
        ];
    }
}
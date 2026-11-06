<?php

namespace App\Http\Resources\Servicio;

use App\Models\Servicio\ServicioHistorialEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * she_estid apunta a un catálogo distinto según she_tipo (sin FK), así que
 * la descripción se resuelve con un mapa cargado UNA vez por request
 * (evita una consulta por fila).
 */
class ServicioHistorialResource extends JsonResource
{
    private static ?array $mapas = null;

    private static function mapas(): array
    {
        return self::$mapas ??= [
            'SERVICIO'    => DB::table('estado_servicio')->pluck('ets_descri', 'ets_id')->all(),
            'INGRESO'     => DB::table('estado_logistica')->pluck('elg_descri', 'elg_id')->all(),
            'EGRESO'      => DB::table('estado_logistica')->pluck('elg_descri', 'elg_id')->all(),
            'PAGO'        => DB::table('estado_pago')->pluck('epa_descri', 'epa_id')->all(),
            'PRESUPUESTO' => DB::table('estado_presupuesto')->pluck('epr_descri', 'epr_id')->all(),
        ];
    }

    public function toArray(Request $request): array
    {
        $mapa = self::mapas()[$this->she_tipo] ?? [];

        return [
            'she_id'       => $this->she_id,
            'she_tipo'     => $this->she_tipo,
            'estado_ant'   => $this->she_estant ? ($mapa[$this->she_estant] ?? null) : null,
            'estado_nuevo' => $this->she_estid ? ($mapa[$this->she_estid] ?? null) : null,
            'she_coment'   => $this->she_coment,
            // Paso 2: contacto con el cliente
            'medio'        => $this->she_medio ? (ServicioHistorialEstado::ETIQUETAS_MEDIO[$this->she_medio] ?? $this->she_medio) : null,
            'resultado'    => $this->she_result ? (ServicioHistorialEstado::ETIQUETAS_RESULTADO[$this->she_result] ?? $this->she_result) : null,
            'she_fecha'    => $this->she_fecha?->format('Y-m-d H:i'),
            'usuario'      => $this->whenLoaded('usuario', fn () => $this->usuario?->persona?->nombre_completo
                                                                   ?? $this->usuario?->usu_login),
        ];
    }
}

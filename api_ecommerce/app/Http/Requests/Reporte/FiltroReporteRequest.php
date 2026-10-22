<?php

namespace App\Http\Requests\Reporte;

use App\Services\Reporte\FiltrosReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros comunes de métricas y reportes.
 *
 * Todos son opcionales: sin fechas se usa el mes en curso y sin
 * sucursal_id se consideran todas las sucursales.
 *
 * Los reportes con filtros propios (proveedor, categoría, técnico...)
 * extienden esta clase y agregan sus reglas con parent::rules().
 */
class FiltroReporteRequest extends FormRequest
{
    /** Rango máximo permitido: protege al servidor de consultas sobre años de datos. */
    private const MAX_DIAS = 1096; // 3 años

    public function authorize(): bool
    {
        return true; // la autorización va en el middleware permiso:
    }

    public function rules(): array
    {
        return [
            'desde'       => ['nullable', 'date_format:Y-m-d'],
            'hasta'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursal,suc_id'],
            'deposito_id' => ['nullable', 'integer', 'exists:deposito,dpo_id'],
            'agrupacion'  => ['nullable', Rule::in(FiltrosReporte::AGRUPACIONES)],
            'limite'      => ['nullable', 'integer', 'min:1', 'max:100'],
            'formato'     => ['nullable', Rule::in(['pdf', 'excel'])],
        ];
    }

    public function messages(): array
    {
        return [
            'desde.date_format'    => 'La fecha desde debe tener el formato AAAA-MM-DD.',
            'hasta.date_format'    => 'La fecha hasta debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'sucursal_id.exists'   => 'La sucursal seleccionada no existe.',
            'deposito_id.exists'   => 'El depósito seleccionado no existe.',
            'agrupacion.in'        => 'La agrupación debe ser día, mes o año.',
            'limite.min'           => 'El límite debe ser al menos 1.',
            'limite.max'           => 'El límite no puede superar 100.',
            'formato.in'           => 'El formato debe ser PDF o Excel.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            [$desde, $hasta] = $this->rango();

            if ($desde->diffInDays($hasta) > self::MAX_DIAS) {
                $v->errors()->add('hasta', 'El rango de fechas no puede superar 3 años.');
            }

            // Un depósito pertenece a una sola sucursal: si vienen ambos, deben coincidir.
            $sucursalId = $this->input('sucursal_id');
            $depositoId = $this->input('deposito_id');

            if ($sucursalId && $depositoId) {
                $coincide = DB::table('deposito')
                    ->where('dpo_id', $depositoId)
                    ->where('dpo_sucid', $sucursalId)
                    ->exists();

                if (!$coincide) {
                    $v->errors()->add('deposito_id', 'El depósito no pertenece a la sucursal seleccionada.');
                }
            }
        });
    }

    /**
     * Construye el objeto de filtros ya validado, con los valores por defecto.
     *
     * @param bool $rangoPorAgrupacion Si no llega "desde", el rango por defecto
     *                                 depende de la agrupación (gráfico de evolución).
     */
    public function filtros(bool $rangoPorAgrupacion = false): FiltrosReporte
    {
        [$desde, $hasta] = $this->rango($rangoPorAgrupacion);

        return new FiltrosReporte(
            desde: $desde,
            hasta: $hasta,
            sucursalId: $this->filled('sucursal_id') ? (int) $this->input('sucursal_id') : null,
            depositoId: $this->filled('deposito_id') ? (int) $this->input('deposito_id') : null,
            agrupacion: $this->input('agrupacion', 'mes'),
            limite: (int) $this->input('limite', 10),
        );
    }

    public function formato(): string
    {
        return $this->input('formato', 'excel');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rango(bool $porAgrupacion = false): array
    {
        $hasta = $this->filled('hasta')
            ? Carbon::createFromFormat('Y-m-d', $this->input('hasta'))->endOfDay()
            : now()->endOfDay();

        if ($this->filled('desde')) {
            return [Carbon::createFromFormat('Y-m-d', $this->input('desde'))->startOfDay(), $hasta];
        }

        $desde = !$porAgrupacion
            ? now()->startOfMonth()
            : match ($this->input('agrupacion', 'mes')) {
                'dia'  => $hasta->copy()->startOfMonth(),
                'anio' => $hasta->copy()->subYears(4)->startOfYear(),
                default => $hasta->copy()->subMonthsNoOverflow(11)->startOfMonth(),
            };

        return [$desde->startOfDay(), $hasta];
    }
}
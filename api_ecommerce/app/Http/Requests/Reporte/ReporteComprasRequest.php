<?php

namespace App\Http\Requests\Reporte;

/** Filtros comunes + propios del reporte de compras. */
class ReporteComprasRequest extends FiltroReporteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedor,pro_id'],
            'dias'         => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
            'dias.min'            => 'El horizonte mínimo es de 1 día.',
            'dias.max'            => 'El horizonte máximo es de 365 días.',
        ];
    }

    public function proveedorId(): ?int
    {
        return $this->filled('proveedor_id') ? (int) $this->input('proveedor_id') : null;
    }

    /** Horizonte de "por vencer" (días hacia adelante). */
    public function dias(): int
    {
        return (int) $this->input('dias', 30);
    }
}

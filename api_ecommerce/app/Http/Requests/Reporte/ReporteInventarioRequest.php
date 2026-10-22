<?php

namespace App\Http\Requests\Reporte;

/** Filtros comunes + propios del reporte de inventario. */
class ReporteInventarioRequest extends FiltroReporteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'categoria_id' => ['nullable', 'integer', 'exists:categoria,cat_id'],
            'marca_id'     => ['nullable', 'integer', 'exists:marca,mar_id'],
            'dias'         => ['nullable', 'integer', 'min:7', 'max:730'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'marca_id.exists'     => 'La marca seleccionada no existe.',
            'dias.min'            => 'El umbral mínimo es de 7 días.',
            'dias.max'            => 'El umbral máximo es de 730 días.',
        ];
    }

    public function categoriaId(): ?int
    {
        return $this->filled('categoria_id') ? (int) $this->input('categoria_id') : null;
    }

    public function marcaId(): ?int
    {
        return $this->filled('marca_id') ? (int) $this->input('marca_id') : null;
    }

    public function dias(): int
    {
        return (int) $this->input('dias', 90);
    }
}
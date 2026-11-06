<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Validation\Rule;

/** Filtros comunes + filtros propios del reporte de ventas. */
class ReporteVentasRequest extends FiltroReporteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'categoria_id' => ['nullable', 'integer', 'exists:categoria,cat_id'],
            'marca_id'     => ['nullable', 'integer', 'exists:marca,mar_id'],
            'por'          => ['nullable', Rule::in(['categoria', 'marca'])],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'marca_id.exists'     => 'La marca seleccionada no existe.',
            'por.in'              => 'La agrupación debe ser por categoría o por marca.',
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

    public function por(): string
    {
        return $this->input('por', 'categoria');
    }
}
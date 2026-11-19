<?php

namespace App\Http\Requests\Venta;

use Illuminate\Foundation\Http\FormRequest;

class AnularCobroCuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rec_motanu' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cob_motanu.required' => 'Explicá por qué se anula el cobro.',
            'cob_motanu.min'      => 'El motivo es demasiado corto.',
        ];
    }
}
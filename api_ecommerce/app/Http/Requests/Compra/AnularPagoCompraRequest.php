<?php
namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;

class AnularPagoCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pgo_motanu' => ['required', 'string', 'min:5', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'pgo_motanu.required' => 'Debe indicar el motivo de la anulación.',
            'pgo_motanu.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
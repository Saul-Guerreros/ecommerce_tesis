<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\FechaSinDomingo;

class AgendarRetiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sse_fecing' => ['required', 'date', 'after_or_equal:today', new FechaSinDomingo],
            'coment'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'sse_fecing.required'       => 'Indique la fecha de retiro.',
            'sse_fecing.after_or_equal' => 'La fecha de retiro no puede ser anterior a hoy.',
            'coment.max'                => 'El comentario no puede superar los 255 caracteres.',
        ];
    }
}

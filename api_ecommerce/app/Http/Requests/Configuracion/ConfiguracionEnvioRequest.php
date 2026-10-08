<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfiguracionEnvioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cfe_modo'         => ['required', Rule::in(['gratis', 'fijo', 'sucursal'])],
            'cfe_costo_fijo'   => ['required_if:cfe_modo,fijo', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'cfe_monto_minimo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('cfe_modo') === 'fijo' && (float) $this->input('cfe_costo_fijo') <= 0) {
                $v->errors()->add('cfe_costo_fijo', 'Con modo "Costo fijo" el costo debe ser mayor a 0.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'cfe_modo.required'            => 'Seleccione el modo de envío.',
            'cfe_modo.in'                   => 'Modo de envío inválido.',
            'cfe_costo_fijo.required_if'    => 'El costo de envío es obligatorio con modo "Costo fijo".',
            'cfe_costo_fijo.numeric'        => 'El costo debe ser un número.',
            'cfe_costo_fijo.min'            => 'El costo no puede ser negativo.',
            'cfe_monto_minimo.numeric'      => 'El monto mínimo debe ser un número.',
            'cfe_monto_minimo.min'          => 'El monto mínimo no puede ser negativo.',
        ];
    }
}
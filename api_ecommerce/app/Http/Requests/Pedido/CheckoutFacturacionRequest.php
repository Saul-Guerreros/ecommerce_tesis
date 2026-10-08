<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esRuc = $this->input('tipo_documento') === 'RUC';
        $usaGuardado = $esRuc && $this->filled('dfi_id');
        $esRucNuevo = $esRuc && !$usaGuardado;

        return [
            'tipo_documento' => ['required', Rule::in(['CI', 'RUC'])],
            'dfi_id'         => ['nullable', 'integer', 'exists:dato_fiscal,dfi_id'],
            'pfa_raznom'     => [Rule::requiredIf($esRucNuevo), 'nullable', 'string', 'max:150'],
            'pfa_nrodoc'     => [Rule::requiredIf($esRucNuevo), 'nullable', 'string', 'max:20'],
            'pfa_telefono'   => [Rule::requiredIf($esRucNuevo), 'nullable', 'string', 'max:20'],
            'pfa_correo'     => [Rule::requiredIf($esRucNuevo), 'nullable', 'email', 'max:100'],
            'pfa_recibe'     => ['required', Rule::in(['CORREO', 'WHATSAPP'])],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documento.required' => 'Elegí el tipo de documento.',
            'pfa_raznom.required'     => 'La razón social es obligatoria.',
            'pfa_nrodoc.required'     => 'El número de RUC es obligatorio.',
            'pfa_telefono.required'   => 'El teléfono es obligatorio.',
            'pfa_correo.required'     => 'El correo es obligatorio.',
            'pfa_recibe.required'     => 'Elegí cómo querés recibir tu comprobante.',
        ];
    }
}
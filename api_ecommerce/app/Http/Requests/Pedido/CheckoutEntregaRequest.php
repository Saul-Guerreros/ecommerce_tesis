<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esRetiro  = $this->input('modalidad') === 'retiro';
        $esEnvio   = $this->input('modalidad') === 'envio';
        $esTercero = $this->input('receptor') === 'tercero';

        return [
            'modalidad' => ['required', Rule::in(['retiro', 'envio'])],
            'receptor'  => ['required', Rule::in(['titular', 'tercero'])],

            'suc_id' => [
                Rule::requiredIf($esRetiro),
                'nullable', 'integer',
                Rule::exists('sucursal', 'suc_id')
                    ->where('suc_estado', 1)
                    ->where('suc_retiro', true),
            ],

            'dir_id' => [
                Rule::requiredIf($esEnvio),
                'nullable', 'integer',
                'exists:direccion,dir_id',
            ],

            'rec_nrodoc' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{5,15}$/'],
            'rec_nombre' => [Rule::requiredIf($esTercero), 'nullable', 'string', 'max:100'],
            'rec_apelli' => [Rule::requiredIf($esTercero), 'nullable', 'string', 'max:100'],
            'rec_telef'  => [
                Rule::requiredIf($esTercero), 'nullable', 'string', 'max:45',
                'regex:/^\+?[0-9\s\-]{6,20}$/',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('modalidad') === 'retiro' && $this->filled('dir_id')) {
                $v->errors()->add('dir_id', 'El retiro en sucursal no lleva direccion de entrega.');
            }

            if ($this->input('modalidad') === 'envio' && $this->filled('suc_id')) {
                $v->errors()->add('suc_id', 'El envio a domicilio no lleva sucursal de retiro.');
            }

            if ($this->input('receptor') === 'tercero' && !$this->filled('rec_nrodoc')) {
                $v->errors()->add('rec_nrodoc', 'El numero de cedula de quien recibe es obligatorio.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'modalidad.required'  => 'Elegi como queres recibir tu pedido.',
            'modalidad.in'        => 'La modalidad de entrega no es valida.',
            'receptor.required'   => 'Indica quien retira o recibe el pedido.',
            'receptor.in'         => 'La opcion de receptor no es valida.',
            'suc_id.required'     => 'Selecciona la sucursal donde vas a retirar.',
            'suc_id.exists'       => 'Esa sucursal no esta habilitada para retiros.',
            'dir_id.required'     => 'Selecciona o agrega una direccion de entrega.',
            'dir_id.exists'       => 'La direccion seleccionada no existe.',
            'rec_nrodoc.regex'    => 'El numero de cedula debe tener entre 5 y 15 digitos, sin puntos.',
            'rec_nombre.required' => 'El primer nombre de quien recibe es obligatorio.',
            'rec_apelli.required' => 'El primer apellido de quien recibe es obligatorio.',
            'rec_telef.required'  => 'El telefono de quien recibe es obligatorio.',
            'rec_telef.regex'     => 'El telefono solo admite numeros, espacios y guiones.',
        ];
    }
}
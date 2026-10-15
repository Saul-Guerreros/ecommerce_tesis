<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\FechaNacimiento;


class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cliId = $this->route('cliente');
        $perId = $this->input('per_id');

        return [
            // --- Datos de persona ---
            'per_id'     => ['nullable','integer','exists:persona,per_id'],
            'per_tpdid'  => ['required','integer','exists:tipo_documento,tpd_id'],
            'per_cedula' => ['required','string','max:50',
                                Rule::unique('persona','per_cedula')
                                    ->where(fn($q) => $q->where('per_tpdid', $this->per_tpdid))
                                    ->ignore($perId, 'per_id')],
            'per_nombr1' => ['required','string','max:45'],
            'per_nombr2' => ['nullable','string','max:45'],
            'per_apell1' => ['required','string','max:50'],
            'per_apell2' => ['nullable','string','max:50'],
            'per_estciv' => ['nullable', Rule::in(['SOLTERO','CASADO','DIVORCIADO','VIUDO','UNION_LIBRE'])],
            'per_fechna' => ['nullable', new FechaNacimiento()],

            // --- Datos de cliente ---
            'cli_tpcid'  => ['required','integer','exists:tipo_cliente,tpc_id'],
            'cli_ruc'    => ['nullable','string','max:45','regex:/^\d{1,15}-\d$/',
                                Rule::unique('cliente','cli_ruc')->ignore($cliId, 'cli_id')],
            'cli_estado' => ['required','integer', Rule::in([0,1])],

            // --- Contactos ---
            'contactos'             => ['required','array','min:1'],
            'contactos.*.con_tipo'  => ['required', Rule::in(['CELULAR','TELEFONO','WHATSAPP','EMAIL'])],
            'contactos.*.con_valor' => ['required','string','max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $contactos = $this->input('contactos', []);

            // Regla de negocio: se coordina por WhatsApp, hace falta un teléfono
            $tienePhone = collect($contactos)->contains(
                fn($c) => in_array($c['con_tipo'] ?? '', ['CELULAR','WHATSAPP'])
            );
            if (!$tienePhone) {
                $v->errors()->add('contactos', 'Debe registrar al menos un celular o WhatsApp.');
            }

            // Formato según el tipo
            foreach ($contactos as $i => $c) {
                $tipo  = $c['con_tipo']  ?? '';
                $valor = $c['con_valor'] ?? '';

                if ($tipo === 'EMAIL' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    $v->errors()->add("contactos.$i.con_valor", 'El correo no tiene un formato válido.');
                }
                if (in_array($tipo, ['CELULAR','WHATSAPP','TELEFONO'])
                    && !preg_match('/^\+?[0-9\s\-]{6,20}$/', $valor)) {
                    $v->errors()->add("contactos.$i.con_valor", 'El número no tiene un formato válido.');
                }
            }

            // Duplicados dentro del mismo envío
            $dup = collect($contactos)->map(fn($c) => ($c['con_tipo'] ?? '').'|'.($c['con_valor'] ?? ''));
            if ($dup->count() !== $dup->unique()->count()) {
                $v->errors()->add('contactos', 'Hay contactos repetidos en el formulario.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'per_tpdid.required'  => 'Debe seleccionar el tipo de documento.',
            'per_tpdid.exists'    => 'El tipo de documento seleccionado no es válido.',
            'per_cedula.required' => 'El número de documento es obligatorio.',
            'per_cedula.unique'   => 'Ya existe una persona registrada con ese documento.',
            'per_nombr1.required' => 'El primer nombre es obligatorio.',
            'per_apell1.required' => 'El primer apellido es obligatorio.',
            'cli_tpcid.required'  => 'Debe seleccionar el tipo de cliente.',
            'cli_tpcid.exists'    => 'El tipo de cliente seleccionado no es válido.',
            'cli_ruc.unique'      => 'El RUC ya está registrado.',
            'cli_ruc.regex'       => 'El RUC debe tener el formato 1234567-8.',
            'cli_estado.required' => 'Debe indicar el estado del cliente.',
            'contactos.required'  => 'Debe registrar al menos un contacto.',
            'contactos.min'       => 'Debe registrar al menos un contacto.',
            'contactos.*.con_tipo.required'  => 'Seleccione el tipo de contacto.',
            'contactos.*.con_valor.required' => 'El valor del contacto es obligatorio.',
            'contactos.*.con_valor.max'      => 'El contacto no puede superar los 100 caracteres.',
        ];
    }
}
<?php

namespace App\Http\Requests\Funcionario;

use App\Models\Funcionario;
use App\Models\Usuario;
use App\Rules\FechaNacimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Rol;

class StoreFuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // En edición la persona se obtiene del funcionario, no del body.
        $funId = $this->route('id');
        $perId = $funId
            ? Funcionario::whereKey($funId)->value('fun_perid')
            : $this->input('per_id');

        $usuId = $this->usuarioId($perId);

        return [
            // --- Datos de persona ---
            'per_id'     => ['nullable', 'integer', 'exists:persona,per_id'],
            'per_tpdid'  => ['required', 'integer', 'exists:tipo_documento,tpd_id'],
            'per_cedula' => ['required', 'string', 'max:50',
                                Rule::unique('persona', 'per_cedula')
                                    ->where(fn($q) => $q->where('per_tpdid', $this->per_tpdid))
                                    ->ignore($perId, 'per_id')],
            'per_nombr1' => ['required', 'string', 'max:45'],
            'per_nombr2' => ['nullable', 'string', 'max:45'],
            'per_apell1' => ['required', 'string', 'max:50'],
            'per_apell2' => ['nullable', 'string', 'max:50'],
            'per_estciv' => ['nullable', Rule::in(['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO', 'UNION_LIBRE'])],
            'per_fechna' => ['nullable', new FechaNacimiento()],

            // --- Datos de funcionario ---
            'fun_crgid'  => ['required', 'integer', 'exists:cargo,crg_id'],
			'rol_id'     => ['required', 'integer', 'exists:rol,rol_id'],
            'fun_fecing' => ['required', 'date_format:Y-m-d'],
            'fun_fecegr' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fun_fecing'],
            'fun_estado' => ['required', 'integer', Rule::in([0, 1])],

            // --- Contactos ---
            'contactos'             => ['required', 'array', 'min:1'],
            'contactos.*.con_tipo'  => ['required', Rule::in(['CELULAR', 'TELEFONO', 'WHATSAPP', 'EMAIL'])],
            'contactos.*.con_valor' => ['required', 'string', 'max:100'],

            // --- Acceso al sistema (opcional) ---
            // usu_login es varchar(30) en la base: sin max:30 un correo largo daría error 500.
            'usu_email'    => ['nullable', 'email', 'max:30',
                                Rule::unique('usuario', 'usu_login')->ignore($usuId, 'usu_id')],
            'usu_password' => ['nullable', 'string', 'min:8', 'max:72'],
        ];
    }

    /** Cuenta de usuario ya existente de la persona (null si todavía no tiene). */
    private function usuarioId($perId): ?int
    {
        return $perId ? Usuario::where('usu_perid', $perId)->value('usu_id') : null;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $contactos = $this->input('contactos', []);
			$rolId = $this->input('rol_id');
            if ($rolId && Rol::whereKey($rolId)->where('rol_descri', 'Cliente')->exists()) {
                $v->errors()->add('rol_id', 'El rol Cliente no se asigna a funcionarios.');
            }

            // Formato según el tipo
            foreach ($contactos as $i => $c) {
                $tipo  = $c['con_tipo']  ?? '';
                $valor = $c['con_valor'] ?? '';

                if ($tipo === 'EMAIL' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    $v->errors()->add("contactos.$i.con_valor", 'El correo no tiene un formato válido.');
                }
                if (in_array($tipo, ['CELULAR', 'WHATSAPP', 'TELEFONO'])
                    && !preg_match('/^\+?[0-9\s\-]{6,20}$/', $valor)) {
                    $v->errors()->add("contactos.$i.con_valor", 'El número no tiene un formato válido.');
                }
            }

            // Acceso: si la persona todavía no tiene cuenta, correo y contraseña van juntos.
            $perId = $this->route('id')
                ? Funcionario::whereKey($this->route('id'))->value('fun_perid')
                : $this->input('per_id');
            if (!$this->usuarioId($perId)) {
                $email = trim((string) $this->input('usu_email'));
                $pass  = (string) $this->input('usu_password');
                if ($email !== '' && $pass === '') {
                    $v->errors()->add('usu_password', 'Ingrese una contraseña para crear el acceso.');
                }
                if ($pass !== '' && $email === '') {
                    $v->errors()->add('usu_email', 'Ingrese el correo de acceso.');
                }
            }

            // Duplicados dentro del mismo envío
            $dup = collect($contactos)->map(fn($c) => ($c['con_tipo'] ?? '') . '|' . ($c['con_valor'] ?? ''));
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
            'fun_crgid.required'  => 'Debe seleccionar el cargo.',
            'fun_crgid.exists'    => 'El cargo seleccionado no es válido.',
			'rol_id.required'     => 'Debe seleccionar el rol.',
            'rol_id.exists'       => 'El rol seleccionado no es válido.',
            'usu_email.email'     => 'El correo de acceso no tiene un formato válido.',
            'usu_email.max'       => 'El correo de acceso no puede superar los 30 caracteres.',
            'usu_email.unique'    => 'Ya existe una cuenta con ese correo.',
            'usu_password.min'    => 'La contraseña debe tener al menos 8 caracteres.',
            'usu_password.max'    => 'La contraseña no puede superar los 72 caracteres.',
            'fun_fecing.required'    => 'La fecha de ingreso es obligatoria.',
            'fun_fecing.date_format' => 'La fecha de ingreso no es válida.',
            'fun_fecegr.date_format' => 'La fecha de egreso no es válida.',
            'fun_fecegr.after_or_equal' => 'La fecha de egreso no puede ser anterior a la de ingreso.',
            'fun_estado.required' => 'Debe indicar el estado del funcionario.',
            'contactos.required'  => 'Debe registrar al menos un contacto.',
            'contactos.min'       => 'Debe registrar al menos un contacto.',
            'contactos.*.con_tipo.required'  => 'Seleccione el tipo de contacto.',
            'contactos.*.con_valor.required' => 'El valor del contacto es obligatorio.',
            'contactos.*.con_valor.max'      => 'El contacto no puede superar los 100 caracteres.',
        ];
    }
}

<?php

namespace App\Http\Requests\Cliente;

use App\Models\TipoDocumento;
use App\Rules\FechaNacimiento;
use App\Services\Cliente\ClienteRapidoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tipo de documento a aplicar: el de la persona si ya tiene documento;
     * si no, el elegido en el formulario (C.I. o pasaporte) o el que ya tenía la ficha.
     */
    public function tipoDocumento(): ?TipoDocumento
    {
        $persona = $this->user('api')?->persona;

        if (filled($persona?->per_cedula)) {
            return TipoDocumento::find($persona->per_tpdid);
        }

        if ($this->filled('tipo_documento')) {
            return TipoDocumento::where('tpd_codigo', $this->input('tipo_documento'))
                ->where('tpd_estado', 1)
                ->whereIn('tpd_codigo', ClienteRapidoService::DOCUMENTOS_MOSTRADOR)
                ->first();
        }

        return $persona?->per_tpdid ? TipoDocumento::find($persona->per_tpdid) : null;
    }

    /** C.I.: solo dígitos. Pasaporte: letras y números en mayúsculas. Misma comparación que el mostrador. */
    private function normalizar(?string $codigo, string $valor): string
    {
        $valor = trim($valor);

        return $codigo === 'PAS'
            ? preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($valor))
            : preg_replace('/\D/', '', $valor);
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('per_cedula')) {
            return;
        }

        $this->merge([
            'per_cedula' => $this->normalizar($this->tipoDocumento()?->tpd_codigo, (string) $this->input('per_cedula')),
        ]);
    }

    public function rules(): array
    {
        $persona   = $this->user('api')?->persona;
        $clienteId = $persona?->cliente?->cli_id;
        $tipo      = $this->tipoDocumento();

        return [
            'per_nombr1' => ['required', 'string', 'max:45'],
            'per_nombr2' => ['nullable', 'string', 'max:45'],
            'per_apell1' => ['required', 'string', 'max:50'],
            'per_apell2' => ['nullable', 'string', 'max:50'],
            'per_fechna' => ['nullable', new FechaNacimiento()],

            'celular' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],

            'cli_ruc' => [
                'nullable', 'string', 'max:45', 'regex:/^\d{1,15}-\d$/',
                Rule::unique('cliente', 'cli_ruc')->ignore($clienteId, 'cli_id'),
            ],

            // max:2048 KB = 2MB
            'avatar' => ['nullable', 'image', 'max:2048'],

            'tipo_documento' => ['nullable', Rule::in(ClienteRapidoService::DOCUMENTOS_MOSTRADOR)],

            // El documento solo se acepta mientras la persona todavía no tiene uno.
            'per_cedula' => [
                'nullable', 'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($persona, $tipo) {
                    if (!$value) {
                        return;
                    }

                    // Ya tiene documento: no se modifica (reenviar el mismo valor es válido).
                    if (filled($persona?->per_cedula)) {
                        if ($value !== $this->normalizar($tipo?->tpd_codigo, $persona->per_cedula)) {
                            $fail('El documento ya fue registrado y no se puede modificar. Contactá con la tienda para corregirlo.');
                        }

                        return;
                    }

                    if (!$tipo) {
                        $fail('Seleccioná el tipo de documento.');

                        return;
                    }

                    $esPasaporte = $tipo->tpd_codigo === 'PAS';
                    $formatoOk   = $esPasaporte
                        ? preg_match('/^[A-Z0-9]{4,20}$/', $value)
                        : preg_match('/^\d{4,10}$/', $value);

                    if (!$formatoOk) {
                        $fail($esPasaporte
                            ? 'El pasaporte debe tener entre 4 y 20 letras o números, sin espacios ni guiones.'
                            : 'La cédula debe contener solo números (entre 4 y 10).');

                        return;
                    }

                    // Mismo criterio que el mostrador: único por tipo + número normalizado.
                    $otra = app(ClienteRapidoService::class)->personaConDocumento($tipo->tpd_id, $value);
                    if ($otra && $otra->per_id !== $persona?->per_id) {
                        $fail('Ese documento ya está registrado en otra cuenta. Si ya te atendimos en la sucursal, '
                            . 'vinculá tu cuenta con el código de tu constancia o acercate a la sucursal.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'per_nombr1.required'   => 'El primer nombre es obligatorio.',
            'per_apell1.required'   => 'El primer apellido es obligatorio.',
            'celular.regex'         => 'El número de celular no tiene un formato válido.',
            'cli_ruc.regex'         => 'El RUC debe tener el formato 1234567-8.',
            'cli_ruc.unique'        => 'Ese RUC ya está registrado por otro cliente.',
            'avatar.image'          => 'El archivo debe ser una imagen.',
            'avatar.max'            => 'La imagen no puede superar los 2MB.',
            'tipo_documento.in'     => 'Seleccioná cédula o pasaporte.',
        ];
    }
}
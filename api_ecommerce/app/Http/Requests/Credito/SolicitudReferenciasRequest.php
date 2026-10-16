<?php

namespace App\Http\Requests\Credito;

use App\Models\Credito\SolicitudCreditoReferencia;
use App\Models\Credito\SolicitudTemporal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paso 3 del wizard: las dos referencias personales llegan juntas, en
 * un arreglo. El orden del arreglo es el que termina en scf_orden.
 *
 * max:5 y no max:2 a proposito: el minimo lo fija el negocio
 * (MINIMO_REQUERIDO) y hoy el wizard manda exactamente dos, pero la
 * tabla es 1:N y aceptar alguna mas no cuesta nada. El dia que credito
 * pida una tercera referencia, se agrega un sub-paso en Angular y no se
 * toca ni esta clase ni la base.
 */
class SolicitudReferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referencias' => ['required', 'array', 'min:' . SolicitudCreditoReferencia::MINIMO_REQUERIDO, 'max:5'],

            'referencias.*.scf_nombre' => ['required', 'string', 'max:120'],
            'referencias.*.scf_celula' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'referencias.*.scf_relaci' => ['required', Rule::in(SolicitudCreditoReferencia::RELACIONES)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $referencias = $this->input('referencias', []);

            if (!is_array($referencias)) {
                return;
            }

            $celulares = [];

            foreach ($referencias as $i => $referencia) {
                $celular = $this->normalizarCelular($referencia['scf_celula'] ?? '');

                if ($celular === '') {
                    continue;
                }

                // Dos referencias con el mismo numero son, en los hechos,
                // una sola referencia.
                if (in_array($celular, $celulares, true)) {
                    $v->errors()->add(
                        "referencias.{$i}.scf_celula",
                        'Esta referencia tiene el mismo número que otra. Cargá contactos distintos.'
                    );
                }

                // Una referencia tiene que ser un tercero: el propio
                // solicitante no puede referenciarse a si mismo.
                if ($celular === $this->celularDelSolicitante()) {
                    $v->errors()->add(
                        "referencias.{$i}.scf_celula",
                        'No podés usar tu propio número como referencia.'
                    );
                }

                $celulares[] = $celular;
            }
        });
    }

    /**
     * Celular declarado en el Paso 1, leido del borrador. Si el Paso 1
     * todavia no esta guardado devuelve null y la comparacion no aplica:
     * el controlador igual va a rechazar el avance por paso incompleto.
     */
    private function celularDelSolicitante(): ?string
    {
        $usuario = auth('api')->user();

        if (!$usuario) {
            return null;
        }

        $borrador = SolicitudTemporal::where('stm_usuid', $usuario->usu_id)->first();
        $celular  = $borrador?->stm_person['scr_celula'] ?? null;

        return $celular ? $this->normalizarCelular($celular) : null;
    }

    /** "0981 123-456" y "0981123456" son el mismo numero. */
    private function normalizarCelular(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public function messages(): array
    {
        return [
            'referencias.required' => 'Cargá las referencias personales.',
            'referencias.min'      => 'Se requieren al menos ' . SolicitudCreditoReferencia::MINIMO_REQUERIDO . ' referencias personales.',
            'referencias.*.scf_nombre.required' => 'El nombre y apellido de la referencia es obligatorio.',
            'referencias.*.scf_celula.required' => 'El número de celular de la referencia es obligatorio.',
            'referencias.*.scf_celula.regex'    => 'El celular solo admite números, espacios y guiones.',
            'referencias.*.scf_relaci.required' => 'Indicá qué relación tenés con esta referencia.',
            'referencias.*.scf_relaci.in'       => 'La relación seleccionada no es válida.',
        ];
    }
}
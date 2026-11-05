<?php

namespace App\Http\Requests\Pedido;

use App\Models\Pedido\PagoCheque;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del modal [Editar] de la tarjeta Recepción de Cheque.
 *
 * La fecha de cobro es obligatoria SOLO en el diferido: en un cheque a la
 * vista no existe, y pedirla igual obligaría al admin a inventar un dato.
 * La regla condicional vive acá y no en el service porque depende del
 * payload, no del estado del pedido.
 */
class ActualizarChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'   => ['required', Rule::in([PagoCheque::TIPO_VISTA, PagoCheque::TIPO_DIFERIDO])],
            'banco'  => ['required', 'string', 'max:80'],
            'nombre' => ['required', 'string', 'max:150'],
            'nrodoc' => ['required', 'string', 'max:20'],
            'nro'    => ['required', 'string', 'max:20'],

            'fecha_cobro' => [
                Rule::requiredIf(fn () => $this->input('tipo') === PagoCheque::TIPO_DIFERIDO),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'   => 'Indicá si el cheque es a la vista o diferido.',
            'tipo.in'         => 'El tipo de cheque no es válido.',
            'banco.required'  => 'Cargá el banco emisor del cheque.',
            'nombre.required' => 'Cargá el nombre del titular del cheque.',
            'nrodoc.required' => 'Cargá la CI del titular del cheque.',
            'nro.required'    => 'Cargá el número del cheque.',

            'fecha_cobro.required'         => 'Un cheque diferido necesita su fecha de cobro.',
            'fecha_cobro.after_or_equal'   => 'La fecha de cobro no puede ser anterior a hoy.',
        ];
    }
}
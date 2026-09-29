<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DireccionClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dir_barid'      => ['required', 'integer', 'exists:barrio,bar_id'],
            'dir_calid'      => ['required', 'integer', 'exists:calle,cal_id'],
            'dir_nrocasa'    => ['nullable', 'string', 'max:45'],
            'dir_referencia' => ['nullable', 'string', 'max:100'],
            'dir_celular'    => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'dir_telef'      => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'dir_predet'     => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $barId = $this->input('dir_barid');
            $calId = $this->input('dir_calid');

            if ($barId && $calId) {
                // calle depende de barrio (cal_barid), no de ciudad directamente
                $perteneceAlBarrio = DB::table('calle')
                    ->where('cal_id', $calId)
                    ->where('cal_barid', $barId)
                    ->exists();

                if (!$perteneceAlBarrio) {
                    $v->errors()->add('dir_calid', 'La calle seleccionada no pertenece al barrio indicado.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'dir_barid.required' => 'Debes seleccionar un barrio.',
            'dir_calid.required' => 'Debes seleccionar una calle.',
            'dir_barid.exists'   => 'El barrio seleccionado no es válido.',
            'dir_calid.exists'   => 'La calle seleccionada no es válida.',
        ];
    }
}
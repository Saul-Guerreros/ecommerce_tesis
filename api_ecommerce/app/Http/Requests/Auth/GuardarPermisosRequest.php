<?php

namespace App\Http\Requests\Auth;

use App\Support\OpcionesAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPermisosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cambios'              => ['required', 'array', 'min:1'],
            'cambios.*.rol_id'     => ['required', 'integer', 'distinct', 'exists:rol,rol_id'],
            // present (no required): un rol puede quedar sin ninguna opción marcada.
            'cambios.*.permisos'   => ['present', 'array'],
            'cambios.*.permisos.*' => ['string', 'distinct', Rule::in(OpcionesAdmin::codigos())],
        ];
    }

    public function messages(): array
    {
        return [
            'cambios.required'              => 'No hay cambios para guardar.',
            'cambios.*.rol_id.exists'       => 'Uno de los roles indicados no existe.',
            'cambios.*.permisos.*.in'       => 'Una de las opciones indicadas no pertenece al catálogo.',
        ];
    }
}

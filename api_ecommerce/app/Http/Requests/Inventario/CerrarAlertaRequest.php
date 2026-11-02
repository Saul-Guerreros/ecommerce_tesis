<?php
// app/Http/Requests/Inventario/CerrarAlertaRequest.php
namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Atender: observación opcional. Descartar: observación obligatoria,
 * para que quede registrado por qué se ignoró la recomendación.
 */
class CerrarAlertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $descartar = $this->routeIs('*.descartar');

        return [
            'observacion' => [$descartar ? 'required' : 'nullable', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['observacion.required' => 'Indicá el motivo por el que se descarta la alerta.'];
    }
}
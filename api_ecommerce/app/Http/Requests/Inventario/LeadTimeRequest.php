<?php
// app/Http/Requests/Inventario/LeadTimeRequest.php
namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

/** lead_time = null vuelve a usar el valor global de la configuración. */
class LeadTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_time' => ['present', 'nullable', 'integer', 'between:1,180'],
        ];
    }
}
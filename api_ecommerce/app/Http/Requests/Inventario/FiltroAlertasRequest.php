<?php
// app/Http/Requests/Inventario/FiltroAlertasRequest.php
namespace App\Http\Requests\Inventario;

use App\Models\Inventario\AlertaStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroAlertasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado'   => ['sometimes', 'integer', Rule::in([
                AlertaStock::ESTADO_PENDIENTE, AlertaStock::ESTADO_ATENDIDA, AlertaStock::ESTADO_DESCARTADA,
            ])],
            'tipo'     => ['sometimes', 'nullable', Rule::in(AlertaStock::TIPOS)],
            'deposito' => ['sometimes', 'nullable', 'integer', 'exists:deposito,dpo_id'],
            'search'   => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
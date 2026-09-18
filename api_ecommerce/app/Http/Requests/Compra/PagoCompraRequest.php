<?php
namespace App\Http\Requests\Compra;

use App\Models\Compras\CuotaCompra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PagoCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pgo_fecha'   => ['required', 'date'],
            'pgo_importe' => ['required', 'numeric', 'min:0.01'],
            'pgo_formapa' => ['required', 'string', 'in:Efectivo,Transferencia,Cheque,Otro'],
            'pgo_observ'  => ['nullable', 'string', 'max:150'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $cuota = CuotaCompra::with('pagos')->find($this->route('id'));

            if (!$cuota) {
                return;
            }

            if ($cuota->cuo_estado === CuotaCompra::ESTADO_PAGADA) {
                $validator->errors()->add('pgo_importe', 'Esta cuota ya está totalmente pagada.');
                return;
            }

            $saldo = round((float) $cuota->cuo_importe - (float) $cuota->pagos->sum('pgo_importe'), 2);

            if ($this->filled('pgo_importe') && (float) $this->pgo_importe > $saldo) {
                $validator->errors()->add(
                    'pgo_importe',
                    "El importe no puede superar el saldo pendiente de la cuota (Gs. {$saldo})."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'pgo_fecha.required'   => 'La fecha de pago es obligatoria.',
            'pgo_importe.required' => 'El importe es obligatorio.',
            'pgo_importe.min'      => 'El importe debe ser mayor a 0.',
            'pgo_formapa.required' => 'Debe seleccionar la forma de pago.',
        ];
    }
}
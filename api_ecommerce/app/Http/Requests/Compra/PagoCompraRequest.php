<?php
namespace App\Http\Requests\Compra;

use App\Models\Compras\{CompraCab, CuotaCompra};
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
            'cuo_id'         => ['required', 'integer', 'exists:cuota_compra,cuo_id'],
            'pgo_fecha'      => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'pgo_formapa'    => ['required', 'string', 'in:Efectivo,Transferencia,Cheque,Otro'],
            'pgo_referencia' => ['nullable', 'string', 'max:60'],
            'pgo_observ'     => ['nullable', 'string', 'max:150'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $cuota = CuotaCompra::with('compraCab')->find($this->input('cuo_id'));
            if (!$cuota) {
                return;
            }

            if ($cuota->cuo_estado === CuotaCompra::ESTADO_PAGADA) {
                $v->errors()->add('cuo_id', 'Esta cuota ya está pagada.');
                return;
            }

            if ((int) $cuota->compraCab->com_estfac !== CompraCab::ESTADO_CONFIRMADA) {
                $v->errors()->add('cuo_id', 'La factura de esta cuota no está confirmada.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'cuo_id.required'           => 'Debe indicar la cuota a pagar.',
            'cuo_id.exists'              => 'La cuota indicada no existe.',
            'pgo_fecha.required'        => 'La fecha de pago es obligatoria.',
            'pgo_fecha.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
            'pgo_formapa.required'      => 'Debe seleccionar la forma de pago.',
        ];
    }
}
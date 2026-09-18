<?php
namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class NotaCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ncr_numero' => ['required', 'string', 'max:15'],
            'ncr_fecha'  => ['required', 'date'],
            'ncr_motivo' => ['required', 'string', 'max:100'],
            'ncr_obs'    => ['nullable', 'string', 'max:100'],

            'detalle'                => ['required', 'array', 'min:1'],
            'detalle.*.cmd_id'       => ['required', 'integer', 'exists:compra_detalle,cmd_id'],
            'detalle.*.cantidad'     => ['required', 'numeric', 'min:0.0001'],
            'detalle.*.base_gravada' => ['required', 'numeric', 'min:0.01'],
            'detalle.*.iva'          => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $comId = (int) $this->route('id');

            $compra = DB::table('compra_cab')->where('com_id', $comId)->first();

            if (!$compra) {
                $validator->errors()->add('detalle', 'La compra referenciada no existe.');
                return;
            }

            if ((int) $compra->com_estfac !== 1) {
                $validator->errors()->add('detalle', 'Solo se pueden registrar notas de crédito sobre una compra Confirmada.');
                return;
            }

            if ($this->filled('ncr_numero')) {
                $yaExiste = DB::table('nota_credito')
                    ->where('ncr_comid', $comId)
                    ->where('ncr_numero', $this->ncr_numero)
                    ->exists();

                if ($yaExiste) {
                    $validator->errors()->add('ncr_numero', 'Ya existe una Nota de Crédito con ese número para esta compra.');
                }
            }

            foreach ((array) $this->input('detalle', []) as $i => $linea) {
                if (empty($linea['cmd_id'])) {
                    continue;
                }

                $detalle = DB::table('compra_detalle')->where('cmd_id', $linea['cmd_id'])->first();

                if (!$detalle || (int) $detalle->cmd_comid !== $comId) {
                    $validator->errors()->add("detalle.$i.cmd_id", 'Ese ítem no pertenece a la compra indicada.');
                    continue;
                }

                $yaAcreditado = (float) DB::table('det_nota_credito')
                    ->where('dnc_cmdid', $linea['cmd_id'])
                    ->sum('dnc_cantidad');

                $disponible = (float) $detalle->cmd_canfac - $yaAcreditado;

                if (($linea['cantidad'] ?? 0) > $disponible + 0.0001) {
                    $validator->errors()->add(
                        "detalle.$i.cantidad",
                        "La cantidad acreditada supera el saldo disponible de esa línea ({$disponible})."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'ncr_numero.required' => 'El número de la Nota de Crédito es obligatorio.',
            'ncr_fecha.required'  => 'La fecha de la Nota de Crédito es obligatoria.',
            'ncr_motivo.required' => 'Debe indicar el motivo del descuento.',
            'detalle.required'    => 'Debe cargar al menos un ítem.',
            'detalle.min'         => 'Debe cargar al menos un ítem.',
        ];
    }
}
<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\FechaSinDomingo;

/**
 * Wizard C2 (tienda). Llega como multipart/form-data por las fotos.
 * Que la compra (línea de pedido) esté en garantía y la dirección sea del
 * cliente lo valida el Service (depende del usuario autenticado y del estado
 * del sistema).
 */
class SolicitudServicioStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sse_garant' => filter_var($this->input('sse_garant'), FILTER_VALIDATE_BOOLEAN),
            'sse_marca'  => trim((string) $this->input('sse_marca')),
            'sse_modelo' => trim((string) $this->input('sse_modelo')),
            'sse_nroser' => $this->filled('sse_nroser') ? trim((string) $this->input('sse_nroser')) : null,
        ]);
    }

    public function rules(): array
    {
        $kb  = (int) config('servicio.foto_max_kb');
        $max = (int) config('servicio.max_fotos_cliente');

        return [
            'sse_tdiid'  => ['required', 'integer', Rule::exists('tipo_dispositivo', 'tdi_id')->where('tdi_estado', 1)],
            'sse_marca'  => ['required', 'string', 'max:50'],
            'sse_modelo' => ['required', 'string', 'max:80'],
            'sse_nroser' => ['nullable', 'string', 'max:80'],
            'sse_proble' => ['required', 'string', 'min:10', 'max:500'],
            'sse_acceso' => ['nullable', 'string', 'max:255'],

            'sse_garant' => ['required', 'boolean'],
            // Garantía de compra: exige la línea (producto) comprada; el pedido se deduce de ella.
            // Reclamo de un servicio anterior: exige sse_sseori.
            'sse_dpdid'  => ['nullable', Rule::requiredIf(fn () => $this->boolean('sse_garant') && !$this->filled('sse_sseori')), 'integer'],
            'sse_pedid'  => ['nullable', 'integer'],
            'sse_sseori' => ['nullable', 'string', 'max:20'],

            'modalidad'  => ['required', Rule::in(['sucursal', 'domicilio'])],
            'sse_sucid'  => ['nullable', 'required_if:modalidad,sucursal', 'integer'],
            'sse_dirid'  => ['nullable', 'required_if:modalidad,domicilio', 'integer'],
            'sse_fecing' => ['required', 'date', 'after_or_equal:today', new FechaSinDomingo],
            'acepta_condiciones' => ['accepted'],

            'fotos'   => ['nullable', 'array', "max:{$max}"],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$kb}"],
        ];
    }

    public function messages(): array
    {
        return [
            'sse_tdiid.required'   => 'Seleccioná el tipo de equipo.',
            'sse_tdiid.exists'     => 'El tipo de equipo no es válido.',
            'sse_marca.required'   => 'Indicá la marca del equipo.',
            'sse_modelo.required'  => 'Indicá el modelo del equipo.',
            'sse_proble.required'  => 'Describí el problema del equipo.',
            'sse_proble.min'       => 'Contanos un poco más del problema (mínimo 10 caracteres).',
            'sse_proble.max'       => 'La descripción no puede superar los 500 caracteres.',
            'sse_dpdid.required'   => 'Seleccioná el producto que cubre la garantía.',
            'modalidad.required'   => 'Elegí cómo nos hacés llegar el equipo.',
            'sse_sucid.required_if' => 'Seleccioná la sucursal donde vas a llevar el equipo.',
            'sse_dirid.required_if' => 'Seleccioná la dirección de retiro.',
            'sse_fecing.required'  => 'Indicá la fecha.',
            'sse_fecing.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'acepta_condiciones.accepted' => 'Tenés que aceptar las condiciones del servicio.',
            'fotos.max'            => 'Podés adjuntar como máximo :max fotos.',
            'fotos.*.image'        => 'Solo se admiten imágenes.',
            'fotos.*.mimes'        => 'Las fotos deben ser JPG, PNG o WEBP.',
            'fotos.*.max'          => 'Cada foto puede pesar como máximo 5 MB.',
        ];
    }
}

<?php

namespace App\Http\Requests\Configuracion;

use App\Models\Configuracion\PaginaNosotros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaginaNosotrosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el permiso lo controla el middleware de la ruta
    }

    /**
     * Texto plano: se quitan etiquetas HTML y espacios sobrantes antes de
     * validar. Defensa en profundidad (la tienda igual escapa al mostrar).
     */
    protected function prepareForValidation(): void
    {
        $datos = [];

        foreach (['pno_titulo', 'pno_subtitulo', 'pno_historia', 'pno_mision', 'pno_vision'] as $campo) {
            if ($this->has($campo)) {
                $datos[$campo] = $this->limpiar($this->input($campo));
            }
        }

        if (is_array($this->input('pno_valores'))) {
            $datos['pno_valores'] = array_values(array_map(fn ($v) => is_array($v) ? [
                'icono'       => $this->limpiar($v['icono'] ?? null),
                'titulo'      => $this->limpiar($v['titulo'] ?? null),
                'descripcion' => $this->limpiar($v['descripcion'] ?? null),
            ] : $v, $this->input('pno_valores')));
        }

        if (is_array($this->input('pno_cifras'))) {
            $datos['pno_cifras'] = array_values(array_map(fn ($c) => is_array($c) ? [
                'valor'    => $this->limpiar($c['valor'] ?? null),
                'etiqueta' => $this->limpiar($c['etiqueta'] ?? null),
            ] : $c, $this->input('pno_cifras')));
        }

        $this->merge($datos);
    }

    private function limpiar(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }
        $texto = trim(strip_tags($valor));
        return $texto === '' ? null : $texto;
    }

    public function rules(): array
    {
        return [
            'pno_titulo'    => ['required', 'string', 'max:120'],
            'pno_subtitulo' => ['nullable', 'string', 'max:255'],
            'pno_historia'  => ['nullable', 'string', 'max:5000'],
            'pno_mision'    => ['nullable', 'string', 'max:600'],
            'pno_vision'    => ['nullable', 'string', 'max:600'],

            'pno_valores'               => ['nullable', 'array', 'max:' . PaginaNosotros::MAX_VALORES],
            'pno_valores.*'             => ['array'],
            'pno_valores.*.icono'       => ['required', Rule::in(PaginaNosotros::ICONOS)],
            'pno_valores.*.titulo'      => ['required', 'string', 'max:60'],
            'pno_valores.*.descripcion' => ['nullable', 'string', 'max:200'],

            'pno_cifras'            => ['nullable', 'array', 'max:' . PaginaNosotros::MAX_CIFRAS],
            'pno_cifras.*'          => ['array'],
            'pno_cifras.*.valor'    => ['required', 'string', 'max:20'],
            'pno_cifras.*.etiqueta' => ['required', 'string', 'max:60'],

            'pno_publicado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'pno_titulo.required'            => 'El título es obligatorio.',
            'pno_valores.max'                => 'Podés cargar hasta ' . PaginaNosotros::MAX_VALORES . ' valores.',
            'pno_valores.*.icono.in'         => 'Ícono no permitido.',
            'pno_valores.*.titulo.required'  => 'Cada valor necesita un título.',
            'pno_cifras.max'                 => 'Podés cargar hasta ' . PaginaNosotros::MAX_CIFRAS . ' cifras.',
            'pno_cifras.*.valor.required'    => 'Cada cifra necesita un valor.',
            'pno_cifras.*.etiqueta.required' => 'Cada cifra necesita una etiqueta.',
        ];
    }
}
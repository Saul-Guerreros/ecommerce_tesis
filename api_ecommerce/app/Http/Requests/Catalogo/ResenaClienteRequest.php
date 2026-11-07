<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear / editar la reseña del cliente. Mismas reglas en las dos acciones.
 */
class ResenaClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la sesión y el rol los controla ResenaClienteController
    }

    /**
     * Saneamiento ANTES de validar: sin HTML, sin espacios repetidos y como
     * máximo una línea en blanco seguida. Así los largos mínimos/máximos se
     * miden sobre el texto que realmente se va a guardar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'res_titulo' => $this->limpiar($this->input('res_titulo'), false),
            'res_coment' => $this->limpiar($this->input('res_coment'), true),
        ]);
    }

    public function rules(): array
    {
        $sinEnlaces = 'not_regex:/(https?:\/\/|www\.)/i';

        return [
            'res_califi' => ['required', 'integer', 'between:1,5'],
            'res_titulo' => ['nullable', 'string', 'max:80', $sinEnlaces],
            'res_coment' => ['required', 'string', 'min:10', 'max:1000', $sinEnlaces],
        ];
    }

    public function messages(): array
    {
        return [
            'res_califi.required'  => 'Elegí una calificación de 1 a 5 estrellas.',
            'res_califi.between'   => 'La calificación debe ser de 1 a 5 estrellas.',
            'res_titulo.max'       => 'El título admite hasta 80 caracteres.',
            'res_titulo.not_regex' => 'No se permiten enlaces en la reseña.',
            'res_coment.required'  => 'Contanos tu experiencia con el producto.',
            'res_coment.min'       => 'El comentario debe tener al menos 10 caracteres.',
            'res_coment.max'       => 'El comentario admite hasta 1000 caracteres.',
            'res_coment.not_regex' => 'No se permiten enlaces en la reseña.',
        ];
    }

    private function limpiar(mixed $valor, bool $multilinea): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $texto = strip_tags(str_replace("\r", '', $valor));
        $texto = $multilinea
            ? preg_replace(["/[ \t]+/u", "/\n{3,}/"], [' ', "\n\n"], $texto)
            : preg_replace('/\s+/u', ' ', $texto);
        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }
}
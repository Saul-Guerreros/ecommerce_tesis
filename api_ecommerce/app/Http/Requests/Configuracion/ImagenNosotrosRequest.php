<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class ImagenNosotrosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'mimes' revisa el contenido real del archivo. SVG queda fuera a
            // propósito: puede contener JavaScript.
            'imagen' => [
                'required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072',
                'dimensions:min_width=600,min_height=400,max_width=4000,max_height=4000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'imagen.required'   => 'Seleccioná una imagen.',
            'imagen.mimes'      => 'Formato no permitido. Usá JPG, PNG o WEBP.',
            'imagen.max'        => 'La imagen no puede superar 3 MB.',
            'imagen.dimensions' => 'La imagen debe medir entre 600x400 y 4000x4000 px.',
        ];
    }
}
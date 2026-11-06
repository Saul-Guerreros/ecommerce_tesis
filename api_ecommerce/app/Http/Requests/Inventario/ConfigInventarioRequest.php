<?php
// app/Http/Requests/Inventario/ConfigInventarioRequest.php
namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

/** Mismos rangos que los CHECK de la migración (defensa en dos capas). */
class ConfigInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nivel_servicio' => ['required', 'integer', 'between:80,99'],
            'horizonte'      => ['required', 'integer', 'between:1,12'],
            'lead_time'      => ['required', 'integer', 'between:1,180'],
            'cobertura_max'  => ['required', 'integer', 'between:2,52'],
        ];
    }

    /** Traduce los nombres de la API a las columnas de configuracion_tienda. */
    public function columnas(): array
    {
        return [
            'ctn_nivserv'   => $this->integer('nivel_servicio'),
            'ctn_horizonte' => $this->integer('horizonte'),
            'ctn_leadtime'  => $this->integer('lead_time'),
            'ctn_cobmax'    => $this->integer('cobertura_max'),
        ];
    }
}
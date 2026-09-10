<?php
 
namespace App\Http\Resources\Configuracion;
 
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
 
class EmpresaResource extends JsonResource{
    /**
     * 'sucursales_count' no es una columna de la tabla: el controlador la
     * agrega como atributo dinámico del modelo (withCount o asignación
     * manual) antes de pasar la colección/instancia por este Resource.
     * whenNotNull() evita que aparezca como null cuando show()/store()
     * no la calculan (no tiene sentido mostrar "0 sucursales" recién creada
     * una empresa sin haber consultado la tabla sucursal).
     */
    public function toArray(Request $request): array{
        return [
            'emp_id'=> $this->emp_id,
            'emp_razonsocial'=> $this->emp_razonsocial,
            'emp_ruc'=> $this->emp_ruc,
            'emp_telef'=> $this->emp_telef,
            'emp_email'=> $this->emp_email,
            'emp_actividad'=> $this->emp_actividad,
            'emp_estado'=> $this->emp_estado,
            'emp_creado'=> $this->emp_creado,
            'sucursales_count'=> $this->whenNotNull($this->sucursales_count),
        ];
    }
}
 
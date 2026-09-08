<?php
 
namespace App\Http\Requests\Configuracion;
 
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
 
class EmpresaRequest extends FormRequest{
    /**
     * La autorización real (permisos por rol) se resuelve en middleware,
     * no acá. Este Request solo valida forma de los datos.
     */
    public function authorize(): bool{
        return true;
    }
 
    /**
     * Un solo Request para store y update. En edición, 'empresa' llega
     * como parámetro de ruta ({empresa} en apiResource) y se usa para
     * excluirse a sí mismo del UNIQUE de emp_ruc.
     */
    public function rules(): array{
        $empresaId = $this->route('empresa');
 
        return [
            'emp_razonsocial'=> ['required', 'string', 'max:45'],
            'emp_ruc'=> [
                'required',
                'string',
                'max:15',
                Rule::unique('empresa', 'emp_ruc')->ignore($empresaId, 'emp_id'),
            ],
            'emp_telef'=> ['nullable', 'string', 'max:20'],
            'emp_email'=> ['nullable', 'email', 'max:60'],
            'emp_actividad'=> ['nullable', 'string', 'max:50'],
            'emp_estado'=> ['nullable', 'integer', 'in:0,1'],
        ];
    }
 
    public function messages(): array{
        return [
            'emp_razonsocial.required'=> 'La razón social es obligatoria.',
            'emp_razonsocial.max'=> 'La razón social no puede superar los 45 caracteres.',
            'emp_ruc.required'=> 'El RUC es obligatorio.',
            'emp_ruc.unique'=> 'Ya existe una empresa registrada con ese RUC.',
            'emp_ruc.max'=> 'El RUC no puede superar los 15 caracteres.',
            'emp_email.email'=> 'El correo electrónico no tiene un formato válido.',
        ];
    }
}
 
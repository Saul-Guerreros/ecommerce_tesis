<?php

namespace App\Http\Resources\Seguridad;

use App\Models\TokenVerificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenVerificacionResource extends JsonResource
{
    use Concerns;

    private const TIPOS = [
        TokenVerificacion::TIPO_VERIFICACION_EMAIL      => 'Verificación de correo',
        TokenVerificacion::TIPO_RECUPERACION_PASSWORD   => 'Recuperación de contraseña',
    ];

    // Deliberadamente NO se expone tkv_tokhas (hash del código).
    public function toArray(Request $request): array
    {
        $estado = match (true) {
            (int) $this->tkv_usado === 1 => 'USADO',
            $this->tkv_fecexp->isPast()  => 'EXPIRADO',
            default                      => 'VIGENTE',
        };

        return [
            'tkv_id'     => $this->tkv_id,
            'tkv_tipo'   => $this->tkv_tipo,
            'tipo_label' => self::TIPOS[$this->tkv_tipo] ?? $this->tkv_tipo,
            'tkv_creado' => $this->iso($this->tkv_creado),
            'tkv_fecexp' => $this->iso($this->tkv_fecexp),
            'estado'     => $estado,
            'usuario'    => $this->datosUsuario($this->usuario),
        ];
    }
}

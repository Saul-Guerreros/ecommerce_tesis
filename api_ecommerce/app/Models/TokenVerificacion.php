<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenVerificacion extends Model
{
    protected $table = 'token_verificacion';
    protected $primaryKey = 'tkv_id';
    public $timestamps = false;

    // Tipos soportados
    public const TIPO_VERIFICACION_EMAIL = 'verificacion_email';
    public const TIPO_RECUPERACION_PASSWORD = 'recuperacion_password';

    protected $fillable = [
        'tkv_usuid',
        'tkv_tipo',
        'tkv_tokhas',
        'tkv_fecexp',
        'tkv_usado',
    ];

    protected $casts = [
        'tkv_fecexp' => 'datetime',
        'tkv_creado' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'tkv_usuid', 'usu_id');
    }

    public function estaExpirado(): bool
    {
        return now()->greaterThan($this->tkv_fecexp);
    }
}

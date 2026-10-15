<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    /**
     * Códigos que el sistema busca por nombre (registro de cuentas usa 'CI',
     * datos fiscales usa 'RUC'): no se pueden eliminar, desactivar ni cambiar de código.
     */
    public const CODIGOS_SISTEMA = ['CI', 'RUC'];

    protected $table = 'tipo_documento';
    protected $primaryKey = 'tpd_id';
    public $timestamps = false;

    protected $fillable = [
        'tpd_codigo',
        'tpd_descri',
        'tpd_estado',
    ];

    protected $casts = [
        'tpd_estado' => 'integer',
    ];

    public function personas()
    {
        return $this->hasMany(Persona::class, 'per_tpdid', 'tpd_id');
    }

    public function esDelSistema(): bool
    {
        return in_array($this->tpd_codigo, self::CODIGOS_SISTEMA, true);
    }
}

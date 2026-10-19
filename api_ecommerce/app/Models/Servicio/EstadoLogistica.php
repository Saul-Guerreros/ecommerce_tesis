<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class EstadoLogistica extends Model
{
    protected $table = 'estado_logistica';
    protected $primaryKey = 'elg_id';
    public $timestamps = false;

    public const AMBITO_INGRESO = 'INGRESO';
    public const AMBITO_EGRESO  = 'EGRESO';

    // Ingreso
    public const PENDIENTE       = 'PENDIENTE';
    public const RETIRO_AGENDADO = 'RETIRO_AGENDADO';
    public const EN_TRANSITO     = 'EN_TRANSITO';
    public const RECIBIDO        = 'RECIBIDO';
    // Egreso
    public const NO_DEFINIDO     = 'NO_DEFINIDO';
    public const ESPERA_RETIRO   = 'ESPERA_RETIRO';
    public const DESPACHADO      = 'DESPACHADO';
    public const ENTREGADO       = 'ENTREGADO';

    protected $fillable = ['elg_codigo', 'elg_descri', 'elg_ambito', 'elg_estado'];

    protected $casts = ['elg_estado' => 'integer'];

    public function scopeIngreso($query)
    {
        return $query->where('elg_ambito', self::AMBITO_INGRESO);
    }

    public function scopeEgreso($query)
    {
        return $query->where('elg_ambito', self::AMBITO_EGRESO);
    }

    /** Busca por código validando el ámbito: evita guardar un estado de egreso en sse_elging. */
    public static function porCodigo(string $codigo, string $ambito): self
    {
        return static::where('elg_codigo', $codigo)->where('elg_ambito', $ambito)->firstOrFail();
    }
}

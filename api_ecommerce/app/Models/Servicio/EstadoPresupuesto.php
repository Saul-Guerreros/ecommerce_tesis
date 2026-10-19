<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class EstadoPresupuesto extends Model
{
    protected $table = 'estado_presupuesto';
    protected $primaryKey = 'epr_id';
    public $timestamps = false;

    public const BORRADOR    = 'BORRADOR';
    public const EN_REVISION = 'EN_REVISION';
    public const ENVIADO     = 'ENVIADO';
    public const APROBADO    = 'APROBADO';
    public const RECHAZADO   = 'RECHAZADO';
    public const VENCIDO     = 'VENCIDO';

    /** Estados que el cliente puede ver (los anteriores son internos del taller). */
    public const VISIBLES_CLIENTE = [self::ENVIADO, self::APROBADO, self::RECHAZADO, self::VENCIDO];

    protected $fillable = ['epr_codigo', 'epr_descri', 'epr_estado'];

    protected $casts = ['epr_estado' => 'integer'];

    public static function porCodigo(string $codigo): self
    {
        return static::where('epr_codigo', $codigo)->firstOrFail();
    }
}

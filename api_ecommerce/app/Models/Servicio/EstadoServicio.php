<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class EstadoServicio extends Model
{
    protected $table = 'estado_servicio';
    protected $primaryKey = 'ets_id';
    public $timestamps = false;

    public const SOLICITADA     = 'SOLICITADA';
    public const RECIBIDA       = 'RECIBIDA';
    public const EN_DIAGNOSTICO = 'EN_DIAGNOSTICO';
    public const PRESUPUESTADA  = 'PRESUPUESTADA';
    public const APROBADA       = 'APROBADA';
    public const EN_REPARACION  = 'EN_REPARACION';
    /** Garantía de compra: el taller gestiona el reclamo con la empresa responsable. */
    public const GESTION_GARANTIA = 'GESTION_GARANTIA';
    /** Garantía de compra: el producto está en poder de la empresa. */
    public const EN_EMPRESA       = 'EN_EMPRESA';
    public const LISTA          = 'LISTA';
    public const ENTREGADA      = 'ENTREGADA';
    public const CANCELADA      = 'CANCELADA';

    /** Estados que cierran la solicitud (no admiten más cambios). */
    public const FINALES = [self::ENTREGADA, self::CANCELADA];

    protected $fillable = ['ets_codigo', 'ets_descri', 'ets_visible', 'ets_orden', 'ets_estado'];

    protected $casts = [
        'ets_orden'  => 'integer',
        'ets_estado' => 'integer',
    ];

    public static function porCodigo(string $codigo): self
    {
        return static::where('ets_codigo', $codigo)->firstOrFail();
    }
}

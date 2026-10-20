<?php

namespace App\Models\Credito;

use Illuminate\Database\Eloquent\Model;

/**
 * Catalogo de estados de la solicitud de credito.
 *
 * Mismo patron que EstadoPedido: el codigo PHP se refiere SIEMPRE a las
 * constantes de codigo y nunca al id autonumerico, porque el id depende
 * del orden en que corrio el seeder y puede diferir entre la base local
 * y la del VPS.
 */
class EstadoSolicitud extends Model
{
    protected $table      = 'estado_solicitud';
    protected $primaryKey = 'esc_id';
    public    $timestamps = false;

    const CREATED_AT = 'esc_creado';

    public const PENDIENTE   = 'PENDIENTE';
    public const EN_REVISION = 'EN_REVISION';
    public const APROBADA    = 'APROBADA';
    public const RECHAZADA   = 'RECHAZADA';
    public const ANULADA     = 'ANULADA';

    protected $fillable = ['esc_codigo', 'esc_descri', 'esc_orden', 'esc_estado'];

    protected $casts = [
        'esc_id'     => 'integer',
        'esc_orden'  => 'integer',
        'esc_estado' => 'integer',
        'esc_creado' => 'datetime',
    ];

    public static function porCodigo(string $codigo): self
    {
        return static::where('esc_codigo', $codigo)->firstOrFail();
    }
}
<?php

namespace App\Models\Credito;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCreditoReferencia extends Model
{
    protected $table      = 'solicitud_credito_referencia';
    protected $primaryKey = 'scf_id';
    public    $timestamps = false;

    public const RELACION_FAMILIAR = 'FAMILIAR';
    public const RELACION_LABORAL  = 'LABORAL';
    public const RELACION_AMISTAD  = 'AMISTAD';

    public const RELACIONES = [
        self::RELACION_FAMILIAR,
        self::RELACION_LABORAL,
        self::RELACION_AMISTAD,
    ];

    /** Cantidad que exige hoy el wizard. Cambiarla no requiere migracion. */
    public const MINIMO_REQUERIDO = 2;

    protected $fillable = ['scf_scrid', 'scf_orden', 'scf_nombre', 'scf_celula', 'scf_relaci'];

    protected $casts = [
        'scf_scrid' => 'integer',
        'scf_orden' => 'integer',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCredito::class, 'scf_scrid', 'scr_id');
    }

    public static function etiquetaRelacion(?string $codigo): string
    {
        return match ($codigo) {
            self::RELACION_FAMILIAR => 'Familiar',
            self::RELACION_LABORAL  => 'Laboral',
            self::RELACION_AMISTAD  => 'Amistad',
            default                 => '—',
        };
    }
}
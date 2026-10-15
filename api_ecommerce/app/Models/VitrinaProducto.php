<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitrinaProducto extends Model
{
    protected $table = 'vitrina_producto';
	
	    /**
     * Posición máxima dentro de una sección del Home.
     * Debe coincidir con el límite que usa VitrinaController::home().
     */
    const MAX_ORDEN = 12;

    protected $primaryKey = 'vip_id';

    public $timestamps = false;

    const CREATED_AT = 'vip_creado';
    const UPDATED_AT = null;

    const SECCION_FEATURED = 'featured';
    const SECCION_TRENDING = 'trending';
    const SECCION_SELLING = 'selling';

    const SECCIONES = [
        self::SECCION_FEATURED,
        self::SECCION_TRENDING,
        self::SECCION_SELLING,
    ];

    protected $fillable = [
        'vip_pubid',
        'vip_seccion',
        'vip_orden',
    ];

    protected $casts = [
        'vip_orden' => 'integer',
    ];

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(PublicacionWeb::class, 'vip_pubid', 'pub_id');
    }
}

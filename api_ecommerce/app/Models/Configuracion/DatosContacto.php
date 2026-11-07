<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DatosContacto extends Model
{
    protected $table      = 'datos_contacto';
    protected $primaryKey = 'dco_id';

    const CREATED_AT = null;
    const UPDATED_AT = 'dco_actualizado';

    public const CACHE_PUBLICA = 'tienda.datos_contacto';

    protected $fillable = [
        'dco_titulo', 'dco_intro', 'dco_direccion', 'dco_referencia', 'dco_telefono',
        'dco_email', 'dco_whatsapp', 'dco_whatsapp_mensaje', 'dco_horario',
        'dco_mapa_lat', 'dco_mapa_lng', 'dco_mapa_zoom',
        'dco_facebook', 'dco_instagram', 'dco_youtube', 'dco_tiktok',
        'dco_publicado', 'dco_usuid',
    ];

    protected $casts = [
        'dco_id'          => 'integer',
        'dco_mapa_lat'    => 'float',
        'dco_mapa_lng'    => 'float',
        'dco_mapa_zoom'   => 'integer',
        'dco_publicado'   => 'boolean',
        'dco_usuid'       => 'integer',
        'dco_actualizado' => 'datetime',
    ];

    protected static function booted(): void
    {
        $limpiar = fn () => Cache::forget(self::CACHE_PUBLICA);
        static::saved($limpiar);
        static::deleted($limpiar);
    }

    public static function actual(): self
    {
        return static::query()->first()
            ?? static::create(['dco_titulo' => 'Contacto', 'dco_mapa_zoom' => 16]);
    }

    public function setDcoEmailAttribute($value): void
    {
        $this->attributes['dco_email'] = $value ? strtolower(trim($value)) : null;
    }
}
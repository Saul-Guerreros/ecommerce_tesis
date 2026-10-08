<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Services\Chat\InstruccionesChat;
use Illuminate\Support\Facades\Cache;


/**
 * Información de la tienda que el chatbot usa como contexto.
 * Se edita desde el admin; el bot afirma exactamente lo que diga acá.
 */
class FaqTienda extends Model
{
    protected $table      = 'faq_tienda';
    protected $primaryKey = 'fqt_id';
    public    $timestamps = false;

    protected $fillable = [
        'fqt_tema', 'fqt_pregunta', 'fqt_respuesta', 'fqt_activo', 'fqt_orden',
    ];

    protected $casts = [
        'fqt_id'     => 'integer',
        'fqt_activo' => 'boolean',
        'fqt_orden'  => 'integer',
        'fqt_creado' => 'datetime',
    ];
    
    /** Temas válidos (valor => etiqueta para el admin). */
    public const TEMAS = [
        'contacto' => 'Contacto',
        'horarios' => 'Horarios',
        'retiro'   => 'Dirección y retiro',
        'envios'   => 'Envíos',
        'pagos'    => 'Formas de pago',
        'garantia' => 'Garantía',
        'general'  => 'General',
    ];

    /** Solo las activas, en el orden definido por el admin. */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('fqt_activo', true)
            ->orderBy('fqt_tema')
            ->orderBy('fqt_orden');
    }

    protected static function booted(): void
    {
        $limpiar = fn () => Cache::forget(InstruccionesChat::CACHE_FAQ);

        static::saved($limpiar);
        static::deleted($limpiar);
    }
}
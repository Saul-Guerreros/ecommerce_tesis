<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje de una conversación. Además del texto guarda métricas
 * (modelo, tokens, latencia) para evaluar costo y rendimiento.
 */
class ChatMensaje extends Model
{
    protected $table      = 'chat_mensaje';
    protected $primaryKey = 'cms_id';
    public    $timestamps = false;

    public const ROL_USUARIO     = 'usuario';
    public const ROL_ASISTENTE   = 'asistente';
    public const ROL_HERRAMIENTA = 'herramienta';

    protected $fillable = [
        'cnv_id', 'cms_rol', 'cms_contenido', 'cms_herramienta', 'cms_datos',
        'cms_modelo', 'cms_tokens_ent', 'cms_tokens_sal', 'cms_latencia_ms',
    ];

    protected $casts = [
        'cms_id'          => 'integer',
        'cnv_id'          => 'integer',
        'cms_datos'       => 'array',     // jsonb <-> array PHP automáticamente
        'cms_tokens_ent'  => 'integer',
        'cms_tokens_sal'  => 'integer',
        'cms_latencia_ms' => 'integer',
        'cms_creado'      => 'datetime',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ChatConversacion::class, 'cnv_id', 'cnv_id');
    }
}
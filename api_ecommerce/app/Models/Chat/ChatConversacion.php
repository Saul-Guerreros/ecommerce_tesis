<?php

namespace App\Models\Chat;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MÓDULO 6 - ChatBot: conversación de un visitante con el asistente.
 * El invitado se identifica por cnv_token (UUID guardado en el navegador).
 */
class ChatConversacion extends Model
{
    protected $table      = 'chat_conversacion';
    protected $primaryKey = 'cnv_id';
    public    $timestamps = false;

    public const ESTADO_ACTIVA   = 'activa';
    public const ESTADO_DERIVADA = 'derivada';
    public const ESTADO_CERRADA  = 'cerrada';

    protected $fillable = [
        'cnv_token', 'usu_id', 'cnv_estado', 'cnv_motivo_deriv',
        'cnv_ip', 'cnv_ultimo_msj',
    ];

    protected $casts = [
        'cnv_id'         => 'integer',
        'usu_id'         => 'integer',
        'cnv_creado'     => 'datetime',
        'cnv_ultimo_msj' => 'datetime',
    ];

    public function mensajes(): HasMany
    {
        // Se ordena por cms_id y no por cms_creado: en un mismo segundo se
        // guardan varios mensajes (usuario, herramienta, asistente) y el
        // timestamp empataría. El serial siempre respeta el orden de inserción.
        return $this->hasMany(ChatMensaje::class, 'cnv_id', 'cnv_id')
            ->orderBy('cms_id');
    }

     public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usu_id', 'usu_id');
    }

    public function scopeDerivadas(Builder $query): Builder
    {
        return $query->where('cnv_estado', self::ESTADO_DERIVADA);
    }

    public function estaDerivada(): bool
    {
        return $this->cnv_estado === self::ESTADO_DERIVADA;
    }
}
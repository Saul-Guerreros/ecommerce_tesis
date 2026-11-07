<?php

namespace App\Models\Catalogo;

use App\Models\Cliente;
use App\Models\Pedido\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resena extends Model
{
    protected $table = 'resena';
    protected $primaryKey = 'res_id';

    const CREATED_AT = 'res_creado';
    const UPDATED_AT = 'res_actual';

    public const VISIBLE = 1;
    public const OCULTA  = 0;

    // res_estado, res_motocu, res_respue, res_fecres y res_usures NO están
    // en fillable: solo los cambia el ResenaService desde el panel admin,
    // nunca un payload del cliente.
    protected $fillable = [
        'res_prdid', 'res_grpid', 'res_cliid', 'res_pedid',
        'res_califi', 'res_titulo', 'res_coment',
    ];

    protected $casts = [
        'res_califi' => 'integer',
        'res_estado' => 'integer',
        'res_editad' => 'boolean',
        'res_fecres' => 'datetime',
        'res_creado' => 'datetime',
        'res_actual' => 'datetime',
    ];

    public function scopeVisibles(Builder $query): Builder
    {
        return $query->where('res_estado', self::VISIBLE);
    }

    public function esVisible(): bool
    {
        return $this->res_estado === self::VISIBLE;
    }

    /** Producto exacto que compró el cliente (la variante). */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'res_prdid', 'prd_id');
    }

    /** Modelo base: agrupa las reseñas de todas sus variantes. */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'res_grpid', 'prd_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'res_cliid', 'cli_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'res_pedid', 'ped_id');
    }

    public function usuarioRespuesta(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'res_usures', 'usu_id');
    }
}
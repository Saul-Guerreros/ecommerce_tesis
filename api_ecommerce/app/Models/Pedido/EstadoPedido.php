<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class EstadoPedido extends Model
{
    protected $table = 'estado_pedido';
    protected $primaryKey = 'est_id';
    public $timestamps = false;
    const CREATED_AT = 'est_creado';

    public const PENDIENTE = 'PENDIENTE';
    public const EN_PREPARACION = 'EN_PREPARACION';
    public const LISTO = 'LISTO';
    public const ENTREGADO = 'ENTREGADO';
    public const CANCELADO = 'CANCELADO';

    protected $fillable = ['est_codigo', 'est_descri', 'est_estado'];

    public static function porCodigo(string $codigo): self
    {
        return static::where('est_codigo', $codigo)->firstOrFail();
    }
}
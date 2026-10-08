<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class TipoEntrega extends Model
{
    protected $table = 'tipo_entrega';
    protected $primaryKey = 'ten_id';
    public $timestamps = false;

    public const RETIRO = 'RETIRO';
    public const ENVIO  = 'ENVIO';

    protected $fillable = ['ten_codigo', 'ten_descri', 'ten_estado'];

    protected $casts = [
        'ten_id'     => 'integer',
        'ten_estado' => 'integer',
        'ten_creado' => 'datetime',
    ];

    public static function porCodigo(string $codigo): self
    {
        return static::where('ten_codigo', $codigo)
            ->where('ten_estado', 1)
            ->firstOrFail();
    }
}
<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class EstadoPago extends Model
{
    protected $table = 'estado_pago';
    protected $primaryKey = 'epa_id';
    public $timestamps = false;
    const CREATED_AT = 'epa_creado';

    public const PENDIENTE = 'PENDIENTE';
    public const CONFIRMADO = 'CONFIRMADO';
    public const RECHAZADO = 'RECHAZADO';
    public const PARCIAL    = 'PARCIAL';   // <-- nuevo

    protected $fillable = ['epa_codigo', 'epa_descri', 'epa_estado'];

    public static function porCodigo(string $codigo): self
    {
        return static::where('epa_codigo', $codigo)->firstOrFail();
    }
}
<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodo_pago';
    protected $primaryKey = 'mpa_id';
    public $timestamps = false;
    const CREATED_AT = 'mpa_creado';

    public const TRANSFERENCIA   = 'TRANSFERENCIA';
    public const BILLETERA_MOVIL = 'BILLETERA_MOVIL';
    public const CONTRAENTREGA   = 'CONTRAENTREGA';

    protected $fillable = ['mpa_codigo', 'mpa_descri', 'mpa_requcom', 'mpa_estado'];

    protected $casts = [
        'mpa_requcom' => 'boolean',
        'mpa_estado'  => 'boolean',
    ];

    public static function porCodigo(string $codigo): self
    {
        return static::where('mpa_codigo', $codigo)->where('mpa_estado', true)->firstOrFail();
    }

    public function scopeActivos($query)
    {
        return $query->where('mpa_estado', true);
    }
}
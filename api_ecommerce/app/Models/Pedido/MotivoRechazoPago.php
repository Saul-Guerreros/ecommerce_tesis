<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class MotivoRechazoPago extends Model
{
    protected $table = 'motivo_rechazo_pago';
    protected $primaryKey = 'mrp_id';
    public $timestamps = false;
    const CREATED_AT = 'mrp_creado';

    public const ILEGIBLE          = 'ILEGIBLE';
    public const MONTO_NO_COINCIDE = 'MONTO_NO_COINCIDE';
    public const OTRA_OPERACION    = 'OTRA_OPERACION';
    public const NO_ENCONTRADO     = 'NO_ENCONTRADO';
    public const OTRO              = 'OTRO';

    protected $fillable = ['mrp_codigo', 'mrp_descri', 'mrp_requdet', 'mrp_estado'];

    protected $casts = [
        'mrp_id'      => 'integer',
        'mrp_requdet' => 'boolean',
        'mrp_estado'  => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('mrp_estado', 1);
    }
}
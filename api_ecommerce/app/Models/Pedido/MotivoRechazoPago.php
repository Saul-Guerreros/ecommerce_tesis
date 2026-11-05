<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class MotivoRechazoPago extends Model
{
    protected $table = 'motivo_rechazo_pago';
    protected $primaryKey = 'mrp_id';
    public $timestamps = false;
    const CREATED_AT = 'mrp_creado';

    // Comprobante
    public const ILEGIBLE       = 'ILEGIBLE';
    public const OTRA_OPERACION = 'OTRA_OPERACION';
    public const NO_ENCONTRADO  = 'NO_ENCONTRADO';

    // Cheque
    public const CHQ_DATOS_NO_COINCIDEN = 'CHQ_DATOS_NO_COINCIDEN';
    public const CHQ_TITULAR_INCORRECTO = 'CHQ_TITULAR_INCORRECTO';
    public const CHQ_FUERA_DE_PLAZO     = 'CHQ_FUERA_DE_PLAZO';

    // Comunes
    public const MONTO_NO_COINCIDE = 'MONTO_NO_COINCIDE';
    public const OTRO              = 'OTRO';

    // Ámbitos
    public const AMBITO_COMPROBANTE = 'COMPROBANTE';
    public const AMBITO_CHEQUE      = 'CHEQUE';
    public const AMBITO_AMBOS       = 'AMBOS';

    protected $fillable = ['mrp_codigo', 'mrp_descri', 'mrp_ambito', 'mrp_requdet', 'mrp_estado'];

    protected $casts = [
        'mrp_id'      => 'integer',
        'mrp_requdet' => 'boolean',
        'mrp_estado'  => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('mrp_estado', 1);
    }

    /**
     * Motivos aplicables a un tipo de evidencia. AMBOS entra siempre:
     * son los motivos que valen para cualquier forma de pago.
     */
    public function scopeDelAmbito($query, string $ambito)
    {
        return $query->whereIn('mrp_ambito', [$ambito, self::AMBITO_AMBOS]);
    }

    public function aplicaA(string $ambito): bool
    {
        return in_array($this->mrp_ambito, [$ambito, self::AMBITO_AMBOS], true);
    }
}
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
    public const CHEQUE          = 'CHEQUE';

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

    /**
     * Métodos que el administrador tiene que verificar antes de liberar la
     * entrega. No es lo mismo que mpa_requcom: el cheque no lleva archivo
     * adjunto, pero tampoco es un pago inmediato como el efectivo contra
     * entrega.
     *
     * Se usa para clasificar el resultado del checkout; sin esto el cheque
     * caería en el caso "Pedido confirmado" del efectivo, que es
     * justamente lo contrario de lo que pide el instructivo.
     */
    public function requiereVerificacion(): bool
    {
        return $this->mpa_requcom || $this->mpa_codigo === self::CHEQUE;
    }
}
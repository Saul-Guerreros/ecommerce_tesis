<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class MotivoNotaCredito extends Model
{
    protected $table      = 'motivo_nota_credito';
    protected $primaryKey = 'mnc_id';
    public    $timestamps = false;
    /** NC de un anticipo de servicio técnico (AnticipoDevolucionService). */
    public const DEVOLUCION_ANTICIPO = 'DANT';

    protected $fillable = [
        'mnc_codigo', 'mnc_descri', 'mnc_afesto', 'mnc_canfij', 'mnc_obsreq',
        'mnc_destex', 'mnc_dnit', 'mnc_estado',
    ];

    protected $casts = [
        'mnc_id'     => 'integer',
        'mnc_afesto' => 'integer',
        'mnc_canfij' => 'integer',
        'mnc_obsreq' => 'integer',
        'mnc_destex' => 'integer',
        'mnc_dnit'   => 'integer',
        'mnc_estado' => 'integer',
    ];

    /** La cantidad no se tipea: es el saldo de unidades vigentes. */
    public function cantidadFija(): bool
    {
        return $this->mnc_canfij === 1;
    }

    public function exigeObservacion(): bool
    {
        return $this->mnc_obsreq === 1;
    }

    /** Regla de negocio centralizada: nadie pregunta por el codigo. */
    public function afectaStock(): bool
    {
        return $this->mnc_afesto === 1;
    }

    public function scopeActivos($query)
    {
        return $query->where('mnc_estado', 1);
    }

    public function notasCredito()
    {
        return $this->hasMany(NotaCredito::class, 'ncr_mncid', 'mnc_id');
    }
}
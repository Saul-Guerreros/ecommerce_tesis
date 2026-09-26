<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class MotivoNotaCredito extends Model
{
    protected $table      = 'motivo_nota_credito';
    protected $primaryKey = 'mnc_id';
    public    $timestamps = false;

    protected $fillable = ['mnc_codigo', 'mnc_descri', 'mnc_afesto', 'mnc_estado'];

    protected $casts = [
        'mnc_id'     => 'integer',
        'mnc_afesto' => 'integer',
        'mnc_estado' => 'integer',
    ];

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
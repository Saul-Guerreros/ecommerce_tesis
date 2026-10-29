<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class TipoDispositivo extends Model
{
    /** Centinela del punto 7: la solicitud espera que la IA (o un técnico) determine el tipo. */
    public const PENDIENTE = 'PENDIENTE';

    protected $table = 'tipo_dispositivo';
    protected $primaryKey = 'tdi_id';
    public $timestamps = false;

    protected $fillable = ['tdi_codigo', 'tdi_descri', 'tdi_estado'];

    protected $casts = [
        'tdi_estado' => 'integer',
        'tdi_creado' => 'datetime',
    ];

    public function fallas()
    {
        return $this->hasMany(Falla::class, 'fll_tdiid', 'tdi_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('tdi_estado', 1);
    }

    /** Activos que una persona puede elegir (excluye el centinela "Por determinar"). */
    public function scopeSeleccionables($query)
    {
        return $query->where('tdi_estado', 1)->where('tdi_codigo', '!=', self::PENDIENTE);
    }

    public function esPendiente(): bool
    {
        return $this->tdi_codigo === self::PENDIENTE;
    }

    public static function pendienteId(): int
    {
        static $id = null;

        return $id ??= (int) static::where('tdi_codigo', self::PENDIENTE)->value('tdi_id');
    }
}
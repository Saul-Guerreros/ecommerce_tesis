<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;

class Timbrado extends Model
{
    protected $table      = 'timbrado';
    protected $primaryKey = 'tim_id';
    public    $timestamps = false;

    protected $fillable = [
        'tim_empid',
        'tim_tcoid',
        'tim_sucid',
        'tim_numero',
        'tim_establec',
        'tim_puntexp',
        'tim_fechin',
        'tim_fechfn',
        'tim_nroini',
        'tim_nrofin',
        'tim_nroact',
        'tim_observacion',
        'tim_estado',
    ];

    protected $casts = [
        'tim_id'     => 'integer',
        'tim_empid'  => 'integer',
        'tim_tcoid'  => 'integer',
        'tim_sucid'  => 'integer',
        'tim_fechin' => 'date',
        'tim_fechfn' => 'date',
        'tim_nroini' => 'integer',
        'tim_nrofin' => 'integer',
        'tim_nroact' => 'integer',
        'tim_estado' => 'integer',
        'tim_creado' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'tim_empid', 'emp_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'tim_sucid', 'suc_id');
    }

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class, 'tim_tcoid', 'tco_id');
    }

    /**
     * Vigente = activo, dentro de fechas y con numeracion disponible.
     * Es la condicion que debera cumplir el talonario al emitir un
     * documento en las fases de venta.
     */
    public function estaVigente(): bool
    {
        $hoy = now()->startOfDay();

        return $this->tim_estado === 1
            && $this->tim_fechin <= $hoy
            && $this->tim_fechfn >= $hoy
            && $this->tim_nroact < $this->tim_nrofin;
    }

    public function getDisponiblesAttribute(): int
    {
        return $this->tim_nrofin - $this->tim_nroact;
    }

    // Los codigos son numericos de largo fijo: se completan con ceros para
    // que "1" y "001" no generen filas duplicadas bajo el UNIQUE.
    public function setTimEstablecAttribute($value): void
    {
        $this->attributes['tim_establec'] = str_pad(trim($value), 3, '0', STR_PAD_LEFT);
    }

    public function setTimPuntexpAttribute($value): void
    {
        $this->attributes['tim_puntexp'] = str_pad(trim($value), 3, '0', STR_PAD_LEFT);
    }

    public function setTimNumeroAttribute($value): void
    {
        $this->attributes['tim_numero'] = str_pad(trim($value), 8, '0', STR_PAD_LEFT);
    }
}

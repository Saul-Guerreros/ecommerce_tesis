<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class TipoIva extends Model
{
    protected $table      = 'tipo_iva';
    protected $primaryKey = 'tiv_id';
    public    $timestamps = false;

    protected $fillable = [
        'tiv_descri',
        'tiv_tasa',
        'tiv_estado',
    ];

    protected $casts = [
        'tiv_id'     => 'integer',
        'tiv_tasa'   => 'decimal:2',
        'tiv_estado' => 'integer',
        'tiv_creado' => 'datetime',
    ];

    // La descripcion se normaliza para que el UNIQUE no se burle con
    // espacios sobrantes ("IVA 10% " vs "IVA 10%").
    public function setTivDescriAttribute($value): void
    {
        $this->attributes['tiv_descri'] = trim($value);
    }
}

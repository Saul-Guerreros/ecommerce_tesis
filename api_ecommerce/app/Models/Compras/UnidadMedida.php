<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table      = 'unidad_medida';
    protected $primaryKey = 'umd_id';
    public    $timestamps = false;

    protected $fillable = ['umd_codigo', 'umd_nombre', 'umd_decimal', 'umd_estado'];

    protected $casts = [
        'umd_id'      => 'integer',
        'umd_decimal' => 'integer',
        'umd_estado'  => 'integer',
    ];

    public function setUmdCodigoAttribute($value): void
    {
        $this->attributes['umd_codigo'] = $value ? strtoupper(trim($value)) : $value;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    public $timestamps = false;

    protected $table = 'unidad_medida';

    protected $primaryKey = 'umd_id';

    protected $fillable = [
        'umd_codigo',
        'umd_nombre',
        'umd_decimal',
        'umd_estado',
    ];

    protected $casts = [
        'umd_decimal' => 'boolean',
        'umd_estado' => 'integer',
    ];
}

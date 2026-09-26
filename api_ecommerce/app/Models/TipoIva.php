<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoIva extends Model
{
    public $timestamps = false;

    protected $table = 'tipo_iva';

    protected $primaryKey = 'tiv_id';

    protected $fillable = [
        'tiv_descri',
        'tiv_tasa',
        'tiv_estado',
    ];

    protected $casts = [
        'tiv_tasa' => 'decimal:2',
        'tiv_estado' => 'integer',
    ];
}

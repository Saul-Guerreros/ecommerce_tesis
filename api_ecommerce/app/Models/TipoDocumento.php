<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipo_documento';
    protected $primaryKey = 'tpd_id';
    public $timestamps = false;

    protected $fillable = [
        'tpd_codigo',
        'tpd_descri',
        'tpd_estado',
    ];

    public function personas()
    {
        return $this->hasMany(Persona::class, 'per_tpdid', 'tpd_id');
    }
}

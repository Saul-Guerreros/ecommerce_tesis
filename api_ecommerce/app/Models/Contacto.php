<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    protected $table = 'contacto';
    protected $primaryKey = 'con_id';

    const CREATED_AT = 'con_creado';
    const UPDATED_AT = 'con_modific';

    protected $fillable = [
        'con_perid',
        'con_tipo',
        'con_valor',
        'con_estado',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'con_perid', 'per_id');
    }
}

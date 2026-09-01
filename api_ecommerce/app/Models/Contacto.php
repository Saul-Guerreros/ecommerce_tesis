<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    protected $table = 'contacto';
    protected $primaryKey = 'con_id';
    public $timestamps = false;

    protected $fillable = [
        'con_perid',
        'con_tipo',
        'con_valor',
        'con_estado',
        'con_creado',
        'con_modific',
    ];

    protected $casts = [
        'con_estado' => 'integer',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'con_perid', 'per_id');
    }
}
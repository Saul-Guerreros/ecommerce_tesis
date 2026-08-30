<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    protected $table = 'cargo';
    protected $primaryKey = 'crg_id';
    public $timestamps = false;

    protected $fillable = [
        'crg_descr',
        'crg_estado',
    ];

    public function funcionarios()
    {
        return $this->hasMany(Funcionario::class, 'fun_crgid', 'crg_id');
    }
}

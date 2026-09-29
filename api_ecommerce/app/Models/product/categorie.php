<?php

namespace App\Models\product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class categorie extends Model
{
    protected $table = 'categoria';
    protected $primaryKey = 'cat_id';
    public $timestamps = false;

    protected $fillable = [
        'cat_idpadr',
        'cat_nivel',
        'cat_nombre',
        'cat_icono',
    ];

    public function padre()
    {
        return $this->belongsTo(categorie::class, 'cat_idpadr', 'cat_id');
    }

    public function hijos()
    {
        return $this->hasMany(categorie::class, 'cat_idpadr', 'cat_id');
    }
}

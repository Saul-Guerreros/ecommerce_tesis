<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    public $timestamps = false;

    protected $table = 'marca';

    protected $primaryKey = 'mar_id';

    protected $fillable = [
        'mar_descri',
        'mar_estado',
        'mar_creado',
    ];

    protected $casts = [
        'mar_id'     => 'integer',
        'mar_estado' => 'integer',
        'mar_creado' => 'datetime',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'prd_marid', 'mar_id');
    }
}
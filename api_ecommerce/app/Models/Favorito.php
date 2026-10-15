<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorito extends Model
{
    protected $table = 'favorito';
    protected $primaryKey = 'fav_id';

    const CREATED_AT = 'fav_creado';
    const UPDATED_AT = null;

    protected $fillable = [
        'fav_idusu',
        'fav_idpro',
    ];

    protected $casts = [
        'fav_idusu'  => 'integer',
        'fav_idpro'  => 'integer',
        'fav_creado' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fav_idusu', 'usu_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'fav_idpro', 'prd_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    public $timestamps = false;

    protected $table = 'categoria';

    protected $primaryKey = 'cat_id';

    protected $fillable = [
        'cat_idpadr',
        'cat_nivel',
        'cat_nombre',
        'cat_icono',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'cat_idpadr', 'cat_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(Categoria::class, 'cat_idpadr', 'cat_id');
    }
}

<?php

namespace App\Models\Catalogo;

use App\Models\Categoria;
use App\Models\Product\Atributo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaAtributo extends Model
{
    protected $table      = 'categoria_atributo';
    protected $primaryKey = 'cta_id';
    public    $timestamps = false;

    protected $fillable = [
        'cta_catid',
        'cta_atbid',
        'cta_orden',
        'cta_obliga',
    ];

    protected $casts = [
        'cta_id'     => 'integer',
        'cta_catid'  => 'integer',
        'cta_atbid'  => 'integer',
        'cta_orden'  => 'integer',
        'cta_obliga' => 'integer',
        'cta_creado' => 'datetime',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'cta_catid', 'cat_id');
    }

    public function atributo(): BelongsTo
    {
        return $this->belongsTo(Atributo::class, 'cta_atbid', 'atb_id');
    }
}

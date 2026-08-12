<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

 

    protected $table = 'producto';

    protected $primaryKey = 'prd_id';

    // La tabla solo tiene prd_creado, no tiene updated_at
    const CREATED_AT = 'prd_creado';
    const UPDATED_AT = null;

    protected $fillable = [
        'prd_marid',
        'prd_catid',
        'prd_titulo',
        'prd_codinv',
        'prd_descri',
        'prd_precio',
        'prd_stock',
        'prd_stkmin',
        'prd_estado',
    ];

    protected $casts = [
        'prd_precio' => 'decimal:2',
        'prd_stock' => 'integer',
        'prd_stkmin' => 'integer',
        'prd_estado' => 'integer',
        'prd_creado' => 'datetime',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'prd_catid', 'cat_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'prd_marid', 'mar_id');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'img_prdid', 'prd_id')
            ->orderBy('img_orden');
    }
}

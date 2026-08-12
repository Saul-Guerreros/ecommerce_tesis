<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagenProducto extends Model
{
    protected $table = 'imagen_producto';

    protected $primaryKey = 'img_id';

    const CREATED_AT = 'img_creado';
    const UPDATED_AT = null;

    protected $fillable = [
        'img_prdid',
        'img_url',
        'img_orden',
        'img_princi',
    ];

    protected $casts = [
        'img_orden' => 'integer',
        'img_princi' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'img_prdid', 'prd_id');
    }
}

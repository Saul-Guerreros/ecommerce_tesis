<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Compras\TipoIva;
use App\Models\Compras\UnidadMedida;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'producto';
    protected $primaryKey = 'prd_id';
    public $timestamps = false;

    protected $fillable = [
        'prd_padre_id',
        'prd_marid',
        'prd_tivid',
        'prd_umdid',
        'prd_codinv',
        'prd_prevta',
        'prd_margen',
        'prd_descri',
        'prd_stkmin',
        'prd_afestk',
        'prd_estado',
        'prd_afestk',

    ];

    protected $casts = [
        'prd_id'      => 'integer',
        'prd_marid'   => 'integer',
        'prd_tivid'   => 'integer',
        'prd_umdid'   => 'integer',
        'prd_prevta'  => 'decimal:2',
        'prd_margen'  => 'decimal:4',
        'prd_stkmin'  => 'decimal:4',
        'prd_afestk'  => 'integer',
        'prd_estado'  => 'integer',
        'prd_creado'  => 'datetime',
    ];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'prd_marid', 'mar_id');
    }

    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'prd_tivid', 'tiv_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'prd_umdid', 'umd_id');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'img_prdid', 'prd_id');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(\App\Models\Compras\Stock::class, 'sto_prdid', 'prd_id');
    }
}
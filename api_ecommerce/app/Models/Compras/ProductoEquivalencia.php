<?php

namespace App\Models\Compras;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class ProductoEquivalencia extends Model
{
    protected $table      = 'producto_equivalencia';
    protected $primaryKey = 'peq_id';
    public    $timestamps = false;

    protected $fillable = ['peq_prdid', 'peq_umdid', 'peq_factor', 'peq_estado'];

    protected $casts = [
        'peq_id'     => 'integer',
        'peq_prdid'  => 'integer',
        'peq_umdid'  => 'integer',
        'peq_factor' => 'decimal:4',
        'peq_estado' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'peq_prdid', 'prd_id');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'peq_umdid', 'umd_id');
    }
}
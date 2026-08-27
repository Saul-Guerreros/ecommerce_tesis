<?php

namespace App\Models\Product;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VariacionProducto extends Model
{
    use HasFactory;

    protected $table = 'variacion_producto';
    protected $primaryKey = 'var_proid';
    public $timestamps = false; // solo existe var_creado, sin updated_at

    protected $fillable = [
        'var_padrid',
        'var_prdid',
        'var_preadi',
        'var_stock',
        'var_stkmin',
        'var_estado',
        'var_creado',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            date_default_timezone_set('America/Lima');
            $model->var_creado = now();
        });
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'var_prdid', 'prd_id');
    }

    /**
     * Propiedades que componen esta combinación (ej: Rojo + M).
     * Relación N:M vía tabla pivote variacion_valor.
     */
    public function propiedades()
    {
        return $this->belongsToMany(
            Propiedad::class,
            'variacion_valor',
            'vva_varid',
            'vva_prpid',
            'var_proid',
            'prp_id'
        );
    }
}

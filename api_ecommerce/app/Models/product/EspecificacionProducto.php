<?php

namespace App\Models\Product;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EspecificacionProducto extends Model
{
    use HasFactory;

    protected $table = 'especificacion_producto';
    protected $primaryKey = 'esp_id';
    public $timestamps = false; // solo existe esp_creado, sin updated_at

    protected $fillable = [
        'esp_atbid',
        'esp_proid',
        'esp_prpid',
        'esp_valibr',
        'esp_valnum',
        'esp_creado',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            date_default_timezone_set('America/Lima');
            $model->esp_creado = now();
        });
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'esp_proid', 'prd_id');
    }

    public function atributo()
    {
        return $this->belongsTo(Atributo::class, 'esp_atbid', 'atb_id');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'esp_prpid', 'prp_id');
    }
}

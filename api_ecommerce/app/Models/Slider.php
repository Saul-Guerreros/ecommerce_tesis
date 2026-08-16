<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slider extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'slider';
    protected $primaryKey = 'sld_id';

   
    public $timestamps = false;

    const DELETED_AT = 'sld_eliminado';

    protected $fillable = [
        'sld_prdid',
        'sld_titulo',
        'sld_subtitulo', 
        'sld_label',     
        'sld_imagen',
        'sld_enlace',
        'sld_orden',
        'sld_tipo',
        'sld_estado',
        'sld_color',    
    ];

    protected $casts = [
        'sld_creado'    => 'datetime',
        'sld_eliminado' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Slider $slider) {
            date_default_timezone_set('America/Lima');
            $slider->sld_creado = Carbon::now();
        });
    }

   
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'sld_prdid', 'prd_id');
    }
}

<?php

namespace App\Models\product;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atributo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'atributo';
    protected $primaryKey = 'atb_id';
    public $timestamps = false;

    const DELETED_AT = 'atb_eliminado';

    protected $fillable = [
        'atb_nombre',
        'atb_tipo',
        'atb_unidad',
        'atb_estado',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Atributo $atributo) {
            date_default_timezone_set('America/Lima');
            $atributo->atb_creado = Carbon::now();
        });
    }

    public function propiedades()
    {
        return $this->hasMany(Propiedad::class, 'prp_atbid', 'atb_id');
    }
}

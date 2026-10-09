<?php

namespace App\Models\product;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Propiedad extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'propiedad';
    protected $primaryKey = 'prp_id';
    public $timestamps = false;

    const DELETED_AT = 'prp_eliminado';

    protected $fillable = [
        'prp_atbid',
        'prp_nombre',
        'prp_codigo',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Propiedad $propiedad) {
            date_default_timezone_set('America/Lima');
            $propiedad->prp_creado = Carbon::now();
        });
    }

    public function atributo()
    {
        return $this->belongsTo(Atributo::class, 'prp_atbid', 'atb_id');
    }
}

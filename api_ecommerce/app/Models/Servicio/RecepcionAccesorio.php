<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class RecepcionAccesorio extends Model
{
    protected $table = 'recepcion_accesorio';
    protected $primaryKey = 'rac_id';
    public $timestamps = false;

    protected $fillable = ['rac_rseid', 'rac_descri', 'rac_devuel'];

    protected $casts = ['rac_devuel' => 'boolean'];

    public function recepcion()
    {
        return $this->belongsTo(RecepcionServicio::class, 'rac_rseid', 'rse_id');
    }
}

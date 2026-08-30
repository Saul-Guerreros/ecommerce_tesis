<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialPassword extends Model
{
    protected $table = 'historial_password';
    protected $primaryKey = 'hpa_id';
    public $timestamps = false;

    protected $fillable = [
        'hpa_pasant',
        'hpa_iporig',
        'hpa_usuid',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'hpa_usuid', 'usu_id');
    }
}

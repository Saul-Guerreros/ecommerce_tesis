<?php

namespace App\Models\Servicio;

use App\Models\TipoIva;
use Illuminate\Database\Eloquent\Model;

class Trabajo extends Model
{
    protected $table = 'trabajo';
    protected $primaryKey = 'trb_id';
    public $timestamps = false;

    protected $fillable = ['trb_codigo', 'trb_descri', 'trb_precio', 'trb_tivid', 'trb_estado'];

    protected $casts = [
        'trb_precio' => 'decimal:2',
        'trb_tivid'  => 'integer',
        'trb_estado' => 'integer',
        'trb_creado' => 'datetime',
    ];

    public function tipoIva()
    {
        return $this->belongsTo(TipoIva::class, 'trb_tivid', 'tiv_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('trb_estado', 1);
    }
}

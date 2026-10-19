<?php

namespace App\Models\Venta;

use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Caja extends Model
{
    public const TIPO_FISICA  = 1;
    public const TIPO_VIRTUAL = 2;

    protected $table      = 'caja';
    protected $primaryKey = 'caj_id';
    public    $timestamps = false;

    protected $fillable = ['caj_sucid', 'caj_puntexp', 'caj_descri', 'caj_tipo', 'caj_estado'];

    protected $casts = [
        'caj_id'     => 'integer',
        'caj_sucid'  => 'integer',
        'caj_tipo'   => 'integer',
        'caj_estado' => 'integer',
        'caj_creado' => 'datetime',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'caj_sucid', 'suc_id');
    }
}
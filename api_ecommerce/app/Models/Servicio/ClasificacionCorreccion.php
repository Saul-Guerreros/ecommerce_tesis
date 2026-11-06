<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClasificacionCorreccion extends Model
{
    public const DISPOSITIVO = 'DISPOSITIVO';
    public const CATEGORIA   = 'CATEGORIA';
    public const URGENCIA    = 'URGENCIA';

    protected $table = 'clasificacion_correccion';
    protected $primaryKey = 'ccr_id';
    public $timestamps = false;

    protected $fillable = ['ccr_clsid', 'ccr_usuid', 'ccr_campo', 'ccr_valant', 'ccr_valnue', 'ccr_motivo', 'ccr_fecha'];

    protected $casts = ['ccr_fecha' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ccr_usuid', 'usu_id');
    }
}
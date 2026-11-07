<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante de un pago del servicio (espejo de PagoComprobante, uno por pago).
 * sco_verifi = validado por el admin. Validar NO es cobrar.
 */
class ServicioComprobante extends Model
{
    protected $table = 'servicio_comprobante';
    protected $primaryKey = 'sco_id';
    public $timestamps = false;
    const CREATED_AT = 'sco_subido';

    protected $fillable = [
        'sco_spaid', 'sco_archiv', 'sco_verifi',
        'sco_observ', 'sco_usuver', 'sco_fechver',
    ];

    protected $casts = [
        'sco_verifi'  => 'boolean',
        'sco_usuver'  => 'integer',
        'sco_subido'  => 'datetime',
        'sco_fechver' => 'datetime',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(ServicioPago::class, 'sco_spaid', 'spa_id');
    }

    public function usuarioValidador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'sco_usuver', 'usu_id');
    }
}
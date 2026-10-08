<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Direccion extends Model
{
    protected $table = 'direccion';
    protected $primaryKey = 'dir_id';

    const CREATED_AT = 'dir_creado';
    const UPDATED_AT = 'dir_modific';

    protected $fillable = [
        'dir_perid',
        'dir_alias',
        'dir_ciuid',
        'dir_barrio',
        'dir_calle',
        'dir_barid',     // deprecado
        'dir_calid',     // deprecado
        'dir_nrocasa',
        'dir_referencia',
        'dir_celular',
        'dir_telef',
        'dir_predet',
        'dir_estado',
    ];

    protected $casts = [
        'dir_id'     => 'integer',
        'dir_perid'  => 'integer',
        'dir_ciuid'  => 'integer',
        'dir_predet' => 'boolean',
        'dir_estado' => 'integer',
    ];

    /**
     * Direcciones vigentes del cliente.
     *
     * El borrado es logico (dir_estado = 0) porque pedido_entrega
     * referencia direccion con ON DELETE RESTRICT y porque la direccion
     * de un pedido es parte de su historial. Se expone como scope y NO
     * como global scope a proposito: PedidoService y los reportes
     * necesitan poder leer direcciones ya dadas de baja.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('dir_estado', 1);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'dir_perid', 'per_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'dir_ciuid', 'ciu_id');
    }

    public function getResumenAttribute(): string
    {
        $partes = array_filter([
            $this->dir_calle,
            $this->dir_nrocasa ? "c/ {$this->dir_nrocasa}" : null,
            $this->dir_barrio,
            $this->ciudad?->ciu_descri,
        ]);

        return implode(', ', $partes);
    }
}
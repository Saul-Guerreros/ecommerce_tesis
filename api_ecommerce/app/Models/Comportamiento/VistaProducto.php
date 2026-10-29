<?php

namespace App\Models\Comportamiento;

use Illuminate\Database\Eloquent\Model;

class VistaProducto extends Model
{
    protected $table = 'vista_producto';
    protected $primaryKey = 'vpr_id';

    const CREATED_AT = 'vpr_creado';
    const UPDATED_AT = null;

    protected $fillable = ['vpr_prdid', 'vpr_busid', 'vpr_usuid', 'vpr_visita'];

    protected $casts = ['vpr_creado' => 'datetime'];
}
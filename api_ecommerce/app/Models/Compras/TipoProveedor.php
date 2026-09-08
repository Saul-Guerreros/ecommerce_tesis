<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class TipoProveedor extends Model
{
    protected $table      = 'tipo_proveedor';
    protected $primaryKey = 'tpr_id';
    public    $timestamps = false;

    protected $fillable = ['tpr_descri', 'tpr_estado'];

    protected $casts = [
        'tpr_id'     => 'integer',
        'tpr_estado' => 'integer',
    ];
}
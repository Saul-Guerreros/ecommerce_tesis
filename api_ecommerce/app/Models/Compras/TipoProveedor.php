<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class TipoProveedor extends Model
{
    protected $table      = 'tipo_proveedor';
    protected $primaryKey = 'tpr_id';
    public    $timestamps = false;

    protected $fillable = [
        'tpr_descri',
        'tpr_estado',
    ];

    protected $casts = [
        'tpr_id'     => 'integer',
        'tpr_estado' => 'integer',
        'tpr_creado' => 'datetime',
    ];

    public function proveedores()
    {
        return $this->hasMany(Proveedor::class, 'pro_tprid', 'tpr_id');
    }

    // Se normaliza para que el UNIQUE no se burle con espacios sobrantes.
    public function setTprDescriAttribute($value): void
    {
        $this->attributes['tpr_descri'] = trim($value);
    }
}

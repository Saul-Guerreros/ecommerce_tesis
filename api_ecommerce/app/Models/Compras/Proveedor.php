<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Ciudad;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table      = 'proveedor';
    protected $primaryKey = 'pro_id';
    public    $timestamps = false;

    protected $fillable = [
        'pro_tprid', 'pro_ciuid', 'pro_ruc', 'pro_razon', 'pro_nomfan',
        'pro_direccion', 'pro_telef', 'pro_email', 'pro_estado',
    ];

    protected $casts = [
        'pro_id'     => 'integer',
        'pro_tprid'  => 'integer',
        'pro_ciuid'  => 'integer',
        'pro_estado' => 'integer',
        'pro_creado' => 'datetime',
    ];

    public function tipoProveedor()
    {
        return $this->belongsTo(TipoProveedor::class, 'pro_tprid', 'tpr_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'pro_ciuid', 'ciu_id');
    }

    public function contactos()
    {
        return $this->hasMany(ContactoProveedor::class, 'cpr_proid', 'pro_id');
    }

    public function setProRucAttribute($value): void
    {
        $this->attributes['pro_ruc'] = $value ? strtoupper(trim($value)) : $value;
    }

    public function setProEmailAttribute($value): void
    {
        $this->attributes['pro_email'] = $value ? strtolower(trim($value)) : null;
    }
}
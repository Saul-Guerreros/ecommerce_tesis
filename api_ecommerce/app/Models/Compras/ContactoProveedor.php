<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class ContactoProveedor extends Model
{
    protected $table      = 'contacto_proveedor';
    protected $primaryKey = 'cpr_id';
    public    $timestamps = false;

    protected $fillable = [
        'cpr_proid', 'cpr_nombre', 'cpr_apellido', 'cpr_cargo',
        'cpr_telef', 'cpr_email', 'cpr_princi', 'cpr_estado',
    ];

    protected $casts = [
        'cpr_id'     => 'integer',
        'cpr_proid'  => 'integer',
        'cpr_princi' => 'integer',
        'cpr_estado' => 'integer',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'cpr_proid', 'pro_id');
    }

    public function setCprEmailAttribute($value): void
    {
        $this->attributes['cpr_email'] = $value ? strtolower(trim($value)) : null;
    }
}
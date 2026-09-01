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
        'pro_tprid',
        'pro_ciuid',
        'pro_ruc',
        'pro_razon',
        'pro_nomfan',
        'pro_direccion',
        'pro_telef',
        'pro_email',
        'pro_estado',
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

    /**
     * El contacto que el sistema usa por defecto al comunicarse con el
     * proveedor. Solo puede haber uno activo (uq_cpr_princi_pro).
     */
    public function contactoPrincipal()
    {
        return $this->hasOne(ContactoProveedor::class, 'cpr_proid', 'pro_id')
                    ->where('cpr_princi', 1)
                    ->where('cpr_estado', 1);
    }

    // El RUC se normaliza: sin espacios y en una sola forma, para que el
    // UNIQUE no deje pasar "80012345-6" y " 80012345-6" como distintos.
    public function setProRucAttribute($value): void
    {
        $this->attributes['pro_ruc'] = str_replace(' ', '', trim($value));
    }

    public function setProRazonAttribute($value): void
    {
        $this->attributes['pro_razon'] = trim($value);
    }

    // El email se guarda en minusculas: las direcciones no distinguen
    // mayusculas y asi las comparaciones son predecibles.
    public function setProEmailAttribute($value): void
    {
        $this->attributes['pro_email'] = $value ? strtolower(trim($value)) : null;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatoFiscal extends Model
{
    protected $table = 'dato_fiscal';
    protected $primaryKey = 'dfi_id';
    public $timestamps = false;
    const CREATED_AT = 'dfi_creado';

    protected $fillable = [
        'dfi_perid', 'dfi_tdocid', 'dfi_alias',
        'dfi_numero', 'dfi_razon', 'dfi_telefono',
        'dfi_correo', 'dfi_principal',
    ];

    protected $casts = [
        'dfi_principal' => 'boolean',
        'dfi_creado' => 'datetime',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'dfi_perid', 'per_id');
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'dfi_tdocid', 'tpd_id');
    }
}
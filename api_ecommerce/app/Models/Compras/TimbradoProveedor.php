<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class TimbradoProveedor extends Model
{
    protected $table      = 'timbrado_proveedor';
    protected $primaryKey = 'tpv_id';
    public    $timestamps = false;

    protected $fillable = [
        'tpv_proid', 'tpv_tcoid', 'tpv_numero',
        'tpv_fechin', 'tpv_fechfn', 'tpv_estado',
    ];

    protected $casts = [
        'tpv_id'     => 'integer',
        'tpv_proid'  => 'integer',
        'tpv_tcoid'  => 'integer',
        'tpv_estado' => 'integer',
        'tpv_fechin' => 'date',
        'tpv_fechfn' => 'date',
        'tpv_creado' => 'datetime',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'tpv_proid', 'pro_id');
    }

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class, 'tpv_tcoid', 'tco_id');
    }

        public function compras()
    {
        return $this->hasMany(CompraCab::class, 'com_tpvid', 'tpv_id');
    }

    /**
     * Regla clave: se valida la FECHA DE EMISION contra la vigencia,
     * no la fecha de hoy. Un timbrado vencido hoy sigue siendo valido
     * para facturas emitidas mientras estaba vigente.
     */
    public function cubreFecha(string $fecha): bool
    {
        return $fecha >= $this->tpv_fechin->toDateString()
            && $fecha <= $this->tpv_fechfn->toDateString();
    }

    public function setTpvNumeroAttribute($value): void
    {
        $this->attributes['tpv_numero'] = str_pad(trim($value), 8, '0', STR_PAD_LEFT);
    }

    
}

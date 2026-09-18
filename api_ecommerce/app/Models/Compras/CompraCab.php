<?php
// app/Models/Compras/CompraCab.php
namespace App\Models\Compras;

use App\Models\Compra\Proveedor;
use App\Models\Configuracion\{Sucursal, Deposito};
use Illuminate\Database\Eloquent\Model;

class CompraCab extends Model
{
    protected $table      = 'compra_cab';
    protected $primaryKey = 'com_id';
    public    $timestamps = false;

    const ESTADO_CONFIRMADA = 1;
    const ESTADO_ANULADA    = 2;

    const CONDICION_CONTADO = 0;
    const CONDICION_CREDITO = 1;

    protected $fillable = [
        'com_proid', 'com_depid', 'com_sucid', 'com_usuid', 'com_tcoid',
        'com_proruc', 'com_proraz', 'com_timbra', 'com_timbvn',
        'com_nrodoc', 'com_fchaem', 'com_nrorem',
        'com_condic', 'com_plzdia', 'com_cuotas', 'com_interv', 'com_fchvto',
        'com_exenta', 'com_grav5', 'com_grav10', 'com_iva5', 'com_iva10', 'com_total',
        'com_estfac', 'com_obs',
    ];

    protected $casts = [
        'com_id'     => 'integer',
        'com_fchaem' => 'date',
        'com_fchvto' => 'date',
        'com_timbvn' => 'date',
        'com_exenta' => 'decimal:2',
        'com_grav5'  => 'decimal:2',
        'com_grav10' => 'decimal:2',
        'com_iva5'   => 'decimal:2',
        'com_iva10'  => 'decimal:2',
        'com_total'  => 'decimal:2',
        'com_estfac' => 'integer',
        'com_creado' => 'datetime',
        'com_fecanu' => 'datetime',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'com_proid', 'pro_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'com_depid', 'dpo_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'com_sucid', 'suc_id');
    }

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class, 'com_tcoid', 'tco_id');
    }

    public function detalle()
    {
        return $this->hasMany(CompraDetalle::class, 'cmd_comid', 'com_id');
    }

    public function cuotas()
    {
        return $this->hasMany(CuotaCompra::class, 'cuo_comid', 'com_id');
    }
}
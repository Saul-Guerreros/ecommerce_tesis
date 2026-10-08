<?php

namespace App\Models\Pedido;

use App\Models\DatoFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoFacturacion extends Model
{
    protected $table = 'pedido_facturacion';
    protected $primaryKey = 'pfa_id';
    public $timestamps = false;

    protected $fillable = [
        'pfa_pedid', 'pfa_tipodoc', 'pfa_dfiid', 'pfa_nrodoc',
        'pfa_raznom', 'pfa_telefono', 'pfa_correo', 'pfa_recibe',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pfa_pedid', 'ped_id');
    }

    public function datoFiscal(): BelongsTo
    {
        return $this->belongsTo(DatoFiscal::class, 'pfa_dfiid', 'dfi_id');
    }
}
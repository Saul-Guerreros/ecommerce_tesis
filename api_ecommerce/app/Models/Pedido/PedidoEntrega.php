<?php

namespace App\Models\Pedido;

use App\Models\Configuracion\Sucursal;
use App\Models\Direccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEntrega extends Model
{
    protected $table = 'pedido_entrega';
    protected $primaryKey = 'pen_id';
    public $timestamps = false;

    protected $fillable = [
        'pen_pedid', 'pen_tenid', 'pen_sucid', 'pen_dirid',
        'pen_nrodoc', 'pen_nombre', 'pen_apelli', 'pen_telef',
        'pen_departa', 'pen_ciudad', 'pen_barrio', 'pen_calle',
        'pen_nrocasa', 'pen_referencia',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pen_pedid', 'ped_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'pen_sucid', 'suc_id');
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class, 'pen_dirid', 'dir_id');
    }
}
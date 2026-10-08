<?php

namespace App\Models\Pedido;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedido extends Model
{
    protected $table = 'detalle_pedido';
    protected $primaryKey = 'dpd_id';
    public $timestamps = false;

    protected $fillable = [
        'dpd_pedid', 'dpd_prdid', 'dpd_descri', 'dpd_cantid', 'dpd_precio',
        'dpd_subtot', 'dpd_total', 'dpd_iva', 'dpd_modpag',
        'dpd_numcuo', 'dpd_recargopct', 'dpd_montocuo',
    ];

    protected $casts = [
        'dpd_cantid'     => 'decimal:2',
        'dpd_precio'     => 'decimal:2',
        'dpd_subtot'     => 'decimal:2',
        'dpd_total'      => 'decimal:2',
        'dpd_iva'        => 'decimal:2',
        'dpd_recargopct' => 'decimal:2',
        'dpd_montocuo'   => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'dpd_pedid', 'ped_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'dpd_prdid', 'prd_id');
    }
}
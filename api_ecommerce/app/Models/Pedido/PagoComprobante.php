<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoComprobante extends Model
{
    protected $table = 'pago_comprobante';
    protected $primaryKey = 'pco_id';
    public $timestamps = false;
    const CREATED_AT = 'pco_subido';

    protected $fillable = ['pco_pedid', 'pco_ppaid', 'pco_archiv', 'pco_verifi'];

    protected $casts = [
        'pco_verifi' => 'boolean',
        'pco_subido' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pco_pedid', 'ped_id');
    }

    /**
     * Pago al que pertenece este comprobante. Nullable solo en los pedidos
     * anteriores a la migracion de pago dividido.
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoPago::class, 'pco_ppaid', 'ppa_id');
    }
}
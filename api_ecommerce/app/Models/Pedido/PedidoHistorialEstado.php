<?php

namespace App\Models\Pedido;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoHistorialEstado extends Model
{
    protected $table = 'pedido_historial_estado';
    protected $primaryKey = 'peh_id';
    public $timestamps = false;
    const CREATED_AT = 'peh_fecha';

    protected $fillable = [
        'peh_pedid', 'peh_usuid', 'peh_tipo', 'peh_estant',
        'peh_estid', 'peh_coment', 'peh_ip',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'peh_pedid', 'ped_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'peh_usuid', 'usu_id');
    }
}
<?php

namespace App\Models;

use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cliente extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'cli_id';
    public $timestamps = false;

    protected $fillable = [
        'cli_ruc',
        'cli_estado',
        'cli_perid',
        'cli_tpcid',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'cli_perid', 'per_id');
    }

    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class, 'cli_tpcid', 'tpc_id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'ped_cliid', 'cli_id');
    }

        /**
     * Tablas que referencian al cliente con ON DELETE RESTRICT: si tienen filas, no se puede eliminar.
     * [descripción para el mensaje => [tabla, columna]]
     */
    public const MOVIMIENTOS = [
        'pedidos'                 => ['pedido', 'ped_cliid'],
        'solicitudes de servicio' => ['solicitud_servicio', 'sse_cliid'],
        'ventas'                  => ['venta_cab', 'ven_cliid'],
        'solicitudes de crédito'  => ['solicitud_credito', 'scr_cliid'],
    ];

    /** De los ids recibidos, devuelve los que tienen algún movimiento (una consulta por tabla, no por cliente). */
    public static function idsConMovimientos(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $con = [];
        foreach (self::MOVIMIENTOS as [$tabla, $columna]) {
            $con = array_merge(
                $con,
                DB::table($tabla)->whereIn($columna, $ids)->distinct()->pluck($columna)->all()
            );
        }

        return array_values(array_unique($con));
    }
}

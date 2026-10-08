<?php
// app/Models/Compras/ProductoEquivalencia.php

namespace App\Models\Compras;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductoEquivalencia extends Model
{
    protected $table      = 'producto_equivalencia';
    protected $primaryKey = 'peq_id';
    public    $timestamps = false;

    protected $fillable = ['peq_prdid', 'peq_umdid', 'peq_factor', 'peq_estado'];

    protected $casts = [
        'peq_id'     => 'integer',
        'peq_prdid'  => 'integer',
        'peq_umdid'  => 'integer',
        'peq_factor' => 'decimal:4',
        'peq_estado' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'peq_prdid', 'prd_id');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'peq_umdid', 'umd_id');
    }

    /** Líneas de compra que usaron esta presentación. */
    public function detallesCompra()
    {
        return $this->hasMany(CompraDetalle::class, 'cmd_peqid', 'peq_id');
    }

    /**
     * Presentaciones que puede usar un producto: las PROPIAS + las
     * HEREDADAS de su modelo base (si es variante). Es válido porque la
     * variante siempre comparte la unidad de stock del padre
     * (ProductoController::sincronizarVariantes), así que el factor
     * "1 Caja = 12 unidades" significa lo mismo para toda la familia.
     */
    public function scopeDisponiblesPara(Builder $query, int $productoId): Builder
    {
        return $query->whereIn('peq_prdid', self::idsDuenios($productoId));
    }

    /** [id del producto, id de su padre] (sin null si no es variante). */
    public static function idsDuenios(int $productoId): array
    {
        $padreId = DB::table('producto')->where('prd_id', $productoId)->value('prd_padre_id');

        return array_values(array_filter([$productoId, $padreId]));
    }

    /** 12.0000 → "12" · 0.5000 → "0.5" (para etiquetas legibles). */
    public function getFactorFormateadoAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->peq_factor, 4, '.', ''), '0'), '.');
    }
}
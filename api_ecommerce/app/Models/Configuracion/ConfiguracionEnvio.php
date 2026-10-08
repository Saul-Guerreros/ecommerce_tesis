<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionEnvio extends Model
{
    protected $table = 'configuracion_envio';
    protected $primaryKey = 'cfe_id';
    public $timestamps = false;

    protected $fillable = ['cfe_modo', 'cfe_costo_fijo', 'cfe_monto_minimo'];

    protected $casts = [
        'cfe_id'           => 'integer',
        'cfe_costo_fijo'   => 'decimal:2',
        'cfe_monto_minimo' => 'decimal:2',
        'cfe_creado'       => 'datetime',
        'cfe_actualizado'  => 'datetime',
    ];

    private static ?self $memo = null;

    public static function actual(): self
    {
        return static::$memo ??= static::query()->first()
            ?? static::create(['cfe_modo' => 'fijo', 'cfe_costo_fijo' => 0]);
    }

    /** Costo para los modos resolubles sin stock (gratis/fijo). "sucursal" se resuelve aparte con EnvioService. */
    public function costoEnvioPara(float $subtotal): float
    {
        if ($this->cfe_modo === 'gratis') {
            return 0.0;
        }

        $minimo = (float) ($this->cfe_monto_minimo ?? 0);

        if ($minimo > 0 && $subtotal >= $minimo) {
            return 0.0;
        }

        return (float) ($this->cfe_costo_fijo ?? 0);
    }

    public function sucursal()
    {
        // Sin FK directa: cfe_modo = 'sucursal' no fija una sucursal, la resuelve EnvioService por carrito/ciudad.
    }
}
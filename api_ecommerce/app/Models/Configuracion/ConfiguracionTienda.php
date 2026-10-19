<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionTienda extends Model
{
    protected $table = 'configuracion_tienda';
    protected $primaryKey = 'ctn_id';
    public $timestamps = false;

    protected $fillable = [
    'ctn_pocstk_activo',
    'ctn_pocstk_umbral',
    'ctn_retactivo', 'ctn_retdias', 'ctn_retaviso',
    'ctn_pago_banco', 'ctn_pago_banco_titular', 'ctn_pago_banco_cuenta',
    'ctn_pago_tigo_titular', 'ctn_pago_tigo_numero', 'ctn_pago_tigo_referencia',
    'ctn_pago_efectivo_mensaje',
    ];

    protected $casts = [
        'ctn_id'            => 'integer',
        'ctn_pocstk_activo' => 'boolean',
        'ctn_pocstk_umbral' => 'integer',
        'ctn_retactivo'     => 'boolean',
        'ctn_retdias'       => 'integer',
        'ctn_retaviso'      => 'integer',
        'ctn_creado'        => 'datetime',
    ];

    public const VALORES_INICIALES = [
    'ctn_pocstk_activo' => true,
    'ctn_pocstk_umbral' => 5,
    'ctn_retactivo'     => true,
    'ctn_retdias'       => 5,
    'ctn_retaviso'      => 1,
    'ctn_pago_efectivo_mensaje' => 'Pago en efectivo al momento de la entrega. Confirmar pedido por WhatsApp.',
    ];

    private static ?self $memo = null;

    public static function actual(): self
    {
        return static::$memo ??= static::query()->first()
            ?? static::create(self::VALORES_INICIALES);
    }
}
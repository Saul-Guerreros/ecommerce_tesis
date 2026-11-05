<?php

namespace App\Models\Configuracion;

use App\Models\Pedido\PagoCheque;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionTienda extends Model
{
    protected $table = 'configuracion_tienda';
    protected $primaryKey = 'ctn_id';
    public $timestamps = false;

    public const MENSAJE_CHEQUE_INICIAL = 'El proceso de entrega de los productos iniciará una vez verificado el pago con cheque en la sucursal.';

    public const AVISO_CHEQUE_INICIAL = 'La entrega del cheque perteneciente a esta compra debe realizarse en un plazo máximo de 48 horas. Pasado este tiempo el pedido quedará anulado. El stock y el precio del producto adquirido podrán sufrir variaciones transcurrido este plazo.';

     // ---------- Política de stock del cheque ----------

    public const STOCK_AL_RECIBIR   = 'RECEPCION';
    public const STOCK_AL_ACREDITAR = 'ACREDITACION';


    protected $fillable = [
        'ctn_pocstk_activo',
        'ctn_pocstk_umbral',
        'ctn_retactivo', 'ctn_retdias', 'ctn_retaviso',
        'ctn_pago_banco', 'ctn_pago_banco_titular', 'ctn_pago_banco_cuenta',
        'ctn_pago_tigo_titular', 'ctn_pago_tigo_numero', 'ctn_pago_tigo_referencia',
        'ctn_pago_efectivo_mensaje',
        'ctn_pago_cheque_titular', 'ctn_pago_cheque_mensaje', 'ctn_pago_cheque_aviso',
        'ctn_pago_cheque_vista', 'ctn_pago_cheque_diferido',
        'ctn_pago_cheque_retiro', 'ctn_pago_cheque_envio',
        'ctn_cheque_stock',
    ];

    protected $casts = [
        'ctn_id'            => 'integer',
        'ctn_pocstk_activo' => 'boolean',
        'ctn_pocstk_umbral' => 'integer',
        'ctn_retactivo'     => 'boolean',
        'ctn_retdias'       => 'integer',
        'ctn_retaviso'      => 'integer',
        'ctn_creado'        => 'datetime',
        'ctn_pago_cheque_vista'    => 'boolean',
        'ctn_pago_cheque_diferido' => 'boolean',
        'ctn_pago_cheque_retiro'   => 'boolean',
        'ctn_pago_cheque_envio'    => 'boolean',
    ];

    public const VALORES_INICIALES = [
        'ctn_pocstk_activo' => true,
        'ctn_pocstk_umbral' => 5,
        'ctn_retactivo'     => true,
        'ctn_retdias'       => 5,
        'ctn_retaviso'      => 1,
        'ctn_pago_efectivo_mensaje' => 'Pago en efectivo al momento de la entrega. Confirmar pedido por WhatsApp.',
        'ctn_pago_cheque_mensaje'   => self::MENSAJE_CHEQUE_INICIAL,
        'ctn_pago_cheque_aviso'     => self::AVISO_CHEQUE_INICIAL,
        'ctn_pago_cheque_vista'     => true,
        'ctn_pago_cheque_diferido'  => true,
        'ctn_pago_cheque_retiro'    => true,
        'ctn_pago_cheque_envio'     => false,
        'ctn_cheque_stock'          => 'RECEPCION',
    ];

    private static ?self $memo = null;

    public static function actual(): self
    {
        return static::$memo ??= static::query()->first()
            ?? static::create(self::VALORES_INICIALES);
    }

    /**
     * Tipos de cheque que el admin dejó habilitados. La validación del
     * panel garantiza que hay al menos uno.
     *
     * @return string[]  códigos de PagoCheque (VISTA, DIFERIDO)
     */
    public function tiposChequeHabilitados(): array
    {
        return array_values(array_filter([
            $this->ctn_pago_cheque_vista    ? PagoCheque::TIPO_VISTA    : null,
            $this->ctn_pago_cheque_diferido ? PagoCheque::TIPO_DIFERIDO : null,
        ]));
    }

    /** Qué modalidades de entrega aceptan cheque. Lo usa el Paso 1 de la tienda. */
    public function chequeAceptadoPorModalidad(): array
    {
        return [
            'retiro' => (bool) $this->ctn_pago_cheque_retiro,
            'envio'  => (bool) $this->ctn_pago_cheque_envio,
        ];
    }

    /**
     * ¿Se acepta cheque en un pedido con esta modalidad de entrega?
     * Un código desconocido nunca lo acepta.
     */
    public function aceptaChequeEn(?string $codigoEntrega): bool
    {
        return match ($codigoEntrega) {
            TipoEntrega::RETIRO => (bool) $this->ctn_pago_cheque_retiro,
            TipoEntrega::ENVIO  => (bool) $this->ctn_pago_cheque_envio,
            default             => false,
        };
    }

    public const STOCK_OPCIONES = [
        self::STOCK_AL_RECIBIR   => 'Al recibir el cheque en la sucursal',
        self::STOCK_AL_ACREDITAR => 'Al acreditarse el pago en el banco',
    ];

    /**
     * ¿El stock se descuenta al recibir el papel o recién al cobrarlo?
     *
     * Con entrega en sucursal lo habitual es RECEPCION: se recibe el
     * cheque, se factura y se entrega el mismo día. ACREDITACION protege
     * a la tienda del cheque rebotado, a costa de retener la mercadería
     * hasta que el banco acredite — que en un diferido son semanas.
     */
    public function chequeDescuentaEnRecepcion(): bool
    {
        return ($this->ctn_cheque_stock ?? self::STOCK_AL_RECIBIR) === self::STOCK_AL_RECIBIR;
    }

}
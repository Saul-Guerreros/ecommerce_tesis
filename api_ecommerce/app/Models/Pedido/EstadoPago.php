<?php

namespace App\Models\Pedido;

use Illuminate\Database\Eloquent\Model;

class EstadoPago extends Model
{
    protected $table = 'estado_pago';
    protected $primaryKey = 'epa_id';
    public $timestamps = false;
    const CREATED_AT = 'epa_creado';

    /**
     * El pedido existe y reservo stock, pero el cliente todavia no declaro
     * ningun pago (esta en el Paso 3 del checkout, o lo abandono).
     *
     * Se separa de PENDIENTE a proposito: PENDIENTE significa "el cliente
     * ya pago y el admin tiene que verificarlo", y es lo que alimenta la
     * bandeja de verificacion de pagos. Si se reutilizara ese mismo codigo,
     * todo checkout abandonado apareceria en la bandeja como si hubiera un
     * comprobante esperando revision.
     */
    public const PENDIENTE_PAGO = 'PENDIENTE_PAGO';

    /**
     * El cliente declaró su pago (subió comprobante o eligió contra
     * entrega) y falta que el admin lo verifique. Antes se llamaba
     * PENDIENTE a secas; se renombra para que el código hable igual que
     * el documento de máquinas de estado y no se confunda con
     * PENDIENTE_PAGO, que es un momento anterior y distinto.
     */
    public const PAGO_VERIFICAR = 'PAGO_VERIFICAR';
    public const CONFIRMADO = 'CONFIRMADO';
    public const RECHAZADO = 'RECHAZADO';
    public const PARCIAL    = 'PARCIAL';

    protected $fillable = ['epa_codigo', 'epa_descri', 'epa_estado'];

    public static function porCodigo(string $codigo): self
    {
        return static::where('epa_codigo', $codigo)->firstOrFail();
    }
}
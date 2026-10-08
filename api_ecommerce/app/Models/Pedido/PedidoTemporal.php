<?php

namespace App\Models\Pedido;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoTemporal extends Model
{
    protected $table = 'pedido_temporal';
    protected $primaryKey = 'ptm_id';

    const CREATED_AT = 'ptm_creado';
    const UPDATED_AT = 'ptm_actual';

    public const PASO_ENTREGA = 1;
    public const PASO_FACTURACION = 2;
    public const PASO_CONFIRMACION = 3;

    public const MODO_UNICO = 'unico';
    public const MODO_DIVIDIDO = 'dividido';

    protected $fillable = ['ptm_usuid', 'ptm_paso', 'ptm_entrega', 'ptm_factura', 'ptm_pago'];

    protected $casts = [
        'ptm_usuid'   => 'integer',
        'ptm_paso'    => 'integer',
        'ptm_entrega' => 'array',
        'ptm_factura' => 'array',
        'ptm_pago'    => 'array',
        'ptm_creado'  => 'datetime',
        'ptm_actual'  => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ptm_usuid', 'usu_id');
    }

    public function tieneEntrega(): bool
    {
        return !empty($this->ptm_entrega);
    }

    /**
     * ptm_pago siempre se lee por aca, nunca directo.
     *
     * Antes del pago dividido el JSON era un objeto plano
     * { mpa_codigo, mpa_descri, comprobante_temp }. Ahora es
     * { modo, pagos: [ { mpa_codigo, mpa_descri, monto, comprobante_temp } ] }.
     *
     * Este metodo traduce la forma vieja a la nueva al vuelo, para que los
     * borradores que ya estaban en curso al momento del despliegue puedan
     * terminar su checkout sin romperse. El monto no se conocia en la forma
     * vieja: queda en null y lo resuelve quien confirma (es, por definicion,
     * el total del pedido).
     */
    public function pagoNormalizado(): ?array
    {
        $pago = $this->ptm_pago;

        if (empty($pago)) {
            return null;
        }

        if (isset($pago['pagos'])) {
            return $pago;
        }

        return [
            'modo'  => self::MODO_UNICO,
            'pagos' => [[
                'mpa_codigo'       => $pago['mpa_codigo'],
                'mpa_descri'       => $pago['mpa_descri'] ?? '',
                'monto'            => null,
                'comprobante_temp' => $pago['comprobante_temp'] ?? null,
            ]],
        ];
    }
}
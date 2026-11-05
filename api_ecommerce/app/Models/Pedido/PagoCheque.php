<?php

namespace App\Models\Pedido;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cheque con el que se paga UN pago concreto del pedido.
 *
 * A diferencia del comprobante, acá hay un objeto físico con ciclo de
 * vida propio: se declara, llega (o no) a la sucursal dentro del plazo,
 * se recibe, y recién semanas después se acredita. Ese ciclo es el eje
 * "Cheque" que la gestión muestra junto a los de Pedido y Pago.
 */
class PagoCheque extends Model
{
    protected $table = 'pago_cheque';
    protected $primaryKey = 'pch_id';
    public $timestamps = false;
    const CREATED_AT = 'pch_creado';

    // ---------- Tipos ----------
    public const TIPO_VISTA    = 'VISTA';
    public const TIPO_DIFERIDO = 'DIFERIDO';

    public const DESCRIPCIONES = [
        self::TIPO_VISTA    => 'Cheque a la vista',
        self::TIPO_DIFERIDO => 'Cheque diferido',
    ];

        // ---------- Estados ----------
    public const EN_ESPERA    = 'EN_ESPERA';     // declarado, falta que llegue
    public const DESCARTADO   = 'DESCARTADO';    // el papel era otro; esperando datos correctos
    public const RECIBIDO     = 'RECIBIDO';      // el papel está en la sucursal
    public const ACREDITADO   = 'ACREDITADO';    // el banco pagó el cheque
    public const NO_ENTREGADO = 'NO_ENTREGADO';  // venció el plazo de 48 h
    public const RECHAZADO    = 'RECHAZADO';     // el admin lo rechazó

    public const ESTADOS = [
        self::EN_ESPERA    => 'En espera',
        self::DESCARTADO   => 'Descartado',
        self::RECIBIDO     => 'Recibido',
        self::ACREDITADO   => 'Acreditado',
        self::NO_ENTREGADO => 'No entregado',
        self::RECHAZADO    => 'Rechazado',
    ];

    /** Estados en los que el cheque todavía puede recibirse. */
    public const PENDIENTES = [self::EN_ESPERA, self::DESCARTADO];

    /**
     * El papel está en poder de la tienda. ACREDITADO entra acá: un cheque
     * cobrado no deja de estar recibido, y todo el flujo (cobro, factura,
     * avance del pedido, barrido de vencidos) pregunta por esto.
     */
    public const EN_PODER_TIENDA = [self::RECIBIDO, self::ACREDITADO];

    /** Plazo de entrega del cheque físico, en horas. */
    public const HORAS_PLAZO = 48;

    protected $fillable = [
        'pch_pedid', 'pch_ppaid', 'pch_tipo', 'pch_banco', 'pch_nrodoc',
        'pch_nombre', 'pch_nro', 'pch_fecobr',
        'pch_estado', 'pch_fevcto', 'pch_frecep', 'pch_usurec',
        'pch_mrpid', 'pch_motdet',
    ];

    protected $casts = [
        'pch_fecobr' => 'date',
        'pch_fevcto' => 'datetime',
        'pch_frecep' => 'datetime',
        'pch_creado' => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pch_pedid', 'ped_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoPago::class, 'pch_ppaid', 'ppa_id');
    }

    public function usuarioReceptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'pch_usurec', 'usu_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'pch_mrpid', 'mrp_id');
    }

    /** Descartes y ediciones aplicados sobre este cheque. */
    public function cambios(): HasMany
    {
        return $this->hasMany(PagoChequeCambio::class, 'pcc_pchid', 'pch_id')
            ->orderByDesc('pcc_fecha');
    }

    // ---------- Scopes ----------

    public function scopePendientes($query)
    {
        return $query->whereIn('pch_estado', self::PENDIENTES);
    }

    public function scopeEnPoderTienda($query)
    {
        return $query->whereIn('pch_estado', self::EN_PODER_TIENDA);
    }

    public function scopeVencidos($query)
    {
        return $query->pendientes()
            ->whereNotNull('pch_fevcto')
            ->where('pch_fevcto', '<', now());
    }

    // ---------- Reglas de negocio ----------

    public function esDiferido(): bool
    {
        return $this->pch_tipo === self::TIPO_DIFERIDO;
    }

    public function estaPendiente(): bool
    {
        return in_array($this->pch_estado, self::PENDIENTES, true);
    }

    /**
     * El papel llegó a la sucursal. Sigue siendo true después de
     * acreditarse: la recepción no se deshace al cobrar.
     */
    public function fueRecibido(): bool
    {
        return in_array($this->pch_estado, self::EN_PODER_TIENDA, true);
    }

    /** El banco ya pagó el cheque. */
    public function fueAcreditado(): bool
    {
        return $this->pch_estado === self::ACREDITADO;
    }

    public function vencido(): bool
    {
        return $this->estaPendiente()
            && $this->pch_fevcto !== null
            && $this->pch_fevcto->isPast();
    }

    /**
     * ¿Están los datos mínimos para confirmar la recepción?
     *
     * El número identifica el cheque frente al banco y la fecha de cobro
     * dice cuándo depositarlo. Sin esos dos no se puede confirmar nada,
     * aunque el papel esté sobre el mostrador.
     */
    public function datosCompletos(): bool
    {
        if (blank($this->pch_banco) || blank($this->pch_nombre)
            || blank($this->pch_nrodoc) || blank($this->pch_nro)) {
            return false;
        }

        return !$this->esDiferido() || $this->pch_fecobr !== null;
    }

    /** Plazo de entrega contado desde ahora. */
    public static function calcularVencimiento(): \Illuminate\Support\Carbon
    {
        return now()->addHours(self::HORAS_PLAZO);
    }

    public function descripcionTipo(): string
    {
        return self::DESCRIPCIONES[$this->pch_tipo] ?? $this->pch_tipo;
    }

    public function descripcionEstado(): string
    {
        return self::ESTADOS[$this->pch_estado] ?? $this->pch_estado;
    }

    public static function descripcionMetodo(?string $titular): string
    {
        $titular = trim((string) $titular);

        return $titular !== ''
            ? "Pagá tu compra online con cheque emitido a nombre de {$titular}."
            : 'Pagá tu compra online con cheque.';
    }
}
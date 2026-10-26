<?php

namespace App\Models\Servicio;

use App\Models\Cliente;
use App\Models\Configuracion\Sucursal;
use App\Models\Direccion;
use App\Models\Pedido\DetallePedido;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use App\Models\Usuario;
use App\Models\Venta\VentaCab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudServicio extends Model
{
    protected $table = 'solicitud_servicio';
    protected $primaryKey = 'sse_id';

    /** Canal de entrada: tienda online (wizard C2) o mostrador de la sucursal. */
    public const CANAL_WEB       = 'WEB';
    public const CANAL_MOSTRADOR = 'MOSTRADOR';

    // sse_creado / sse_actual cumplen el rol de created_at / updated_at
    const CREATED_AT = 'sse_creado';
    const UPDATED_AT = 'sse_actual';

    protected $fillable = [
        'sse_numero', 'sse_cliid',
        'sse_tdiid', 'sse_marca', 'sse_modelo', 'sse_nroser', 'sse_proble', 'sse_acceso',
        'sse_garant', 'sse_pedid', 'sse_dpdid', 'sse_sseori',
        'sse_tenid', 'sse_sucid', 'sse_dirid', 'sse_fecing', 'sse_cosret',
        'sse_etsid', 'sse_elging', 'sse_epaid', 'sse_elgegr',
        'sse_subtot', 'sse_impues', 'sse_cosenv', 'sse_total',
        'sse_canal', 'sse_usuid',
    ];

    protected $casts = [
        'sse_garant' => 'boolean',
        'sse_fecing' => 'date',
        'sse_cosret' => 'decimal:2',
        'sse_subtot' => 'decimal:2',
        'sse_impues' => 'decimal:2',
        'sse_cosenv' => 'decimal:2',
        'sse_total'  => 'decimal:2',
        'sse_creado' => 'datetime',
        'sse_actual' => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'sse_cliid', 'cli_id');
    }

    public function tipoDispositivo(): BelongsTo
    {
        return $this->belongsTo(TipoDispositivo::class, 'sse_tdiid', 'tdi_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'sse_pedid', 'ped_id');
    }

    /** Línea de compra (producto) que cubre la garantía solicitada. */
    public function detallePedido(): BelongsTo
    {
        return $this->belongsTo(DetallePedido::class, 'sse_dpdid', 'dpd_id');
    }

    public function solicitudOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sse_sseori', 'sse_id');
    }

    public function tipoEntrega(): BelongsTo
    {
        return $this->belongsTo(TipoEntrega::class, 'sse_tenid', 'ten_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sse_sucid', 'suc_id');
    }

    /** Funcionario que registró la solicitud en el mostrador (null en el canal web). */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'sse_usuid', 'usu_id');
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class, 'sse_dirid', 'dir_id');
    }

    public function estadoServicio(): BelongsTo
    {
        return $this->belongsTo(EstadoServicio::class, 'sse_etsid', 'ets_id');
    }

    public function estadoIngreso(): BelongsTo
    {
        return $this->belongsTo(EstadoLogistica::class, 'sse_elging', 'elg_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'sse_epaid', 'epa_id');
    }

    public function estadoEgreso(): BelongsTo
    {
        return $this->belongsTo(EstadoLogistica::class, 'sse_elgegr', 'elg_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(SolicitudAdjunto::class, 'sad_sseid', 'sse_id');
    }

    public function recepcion(): HasOne
    {
        return $this->hasOne(RecepcionServicio::class, 'rse_sseid', 'sse_id');
    }

    public function ordenServicio(): HasOne
    {
        return $this->hasOne(OrdenServicio::class, 'ose_sseid', 'sse_id');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(EntregaServicio::class, 'ese_sseid', 'sse_id');
    }

    /** Reclamos de garantía abiertos sobre esta solicitud. */
    public function reclamos(): HasMany
    {
        return $this->hasMany(self::class, 'sse_sseori', 'sse_id');
    }

    /** Gestión con la empresa responsable (solo garantía de compra). */
    public function garantiaProveedor(): HasOne
    {
        return $this->hasOne(GarantiaProveedor::class, 'gpr_sseid', 'sse_id');
    }

    /** Último "Registrar contacto con el cliente" (panel: esperando respuesta del presupuesto). */
    public function ultimoContacto(): HasOne
    {
        return $this->hasOne(ServicioHistorialEstado::class, 'she_sseid', 'sse_id')
            ->ofMany(['she_id' => 'max'], fn ($q) => $q->where('she_tipo', ServicioHistorialEstado::CONTACTO));
    }

    /** Pagos del servicio (anticipo, saldo o total), en el orden en que se registraron. */
    public function pagos(): HasMany
    {
        return $this->hasMany(ServicioPago::class, 'spa_sseid', 'sse_id')->orderBy('spa_orden');
    }

    /** Datos fiscales elegidos para facturar (CI o RUC). Sin fila: se factura con la CI. */
    public function facturacion(): HasOne
    {
        return $this->hasOne(ServicioFacturacion::class, 'sfa_sseid', 'sse_id');
    }

    /**
     * Factura VIGENTE del servicio (las anuladas quedan fuera, igual que
     * uq_venta_servicio). Se define de este lado para no modificar VentaCab.
     */
    public function factura(): HasOne
    {
        return $this->hasOne(VentaCab::class, 'ven_sseid', 'sse_id')
            ->where('ven_estado', '<>', VentaCab::ESTADO_ANULADA);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ServicioHistorialEstado::class, 'she_sseid', 'sse_id')->orderBy('she_fecha')->orderBy('she_id');
    }

    // ---------- Ayudas de dominio ----------

    public function esMostrador(): bool
    {
        return $this->sse_canal === self::CANAL_MOSTRADOR;
    }

    /** true = el taller retira el equipo del domicilio (tipo_entrega ENVIO). */
    public function esADomicilio(): bool
    {
        return $this->tipoEntrega?->ten_codigo === TipoEntrega::ENVIO;
    }

    /**
     * Garantía de COMPRA: la cubre la empresa responsable del producto, así que el taller no
     * diagnostica ni presupuesta: gestiona el reclamo con esa empresa. El reclamo de una
     * reparación hecha por el propio taller (sse_sseori) NO entra acá: se repara de nuevo.
     */
    public function esGarantiaDeCompra(): bool
    {
        return (bool) $this->sse_garant && !$this->sse_sseori;
    }

    public function codigoServicio(): ?string
    {
        return $this->estadoServicio?->ets_codigo;
    }

    /**
     * Garantía del TRABAJO: corre desde la entrega por ose_gardia días.
     * Devuelve la fecha de vencimiento o null si no aplica.
     */
    public function garantiaVenceEl(): ?\Illuminate\Support\Carbon
    {
        $orden = $this->ordenServicio;

        if (!$this->entrega || !$orden || $orden->ose_gardia <= 0
            || !in_array($orden->ose_result, [OrdenServicio::REPARADO, OrdenServicio::PARCIAL], true)) {
            return null;
        }

        return $this->entrega->ese_fecha->copy()->addDays($orden->ose_gardia)->endOfDay();
    }

    public function codigoIngreso(): ?string
    {
        return $this->estadoIngreso?->elg_codigo;
    }
}

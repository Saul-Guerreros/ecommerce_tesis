<?php

namespace App\Models\Servicio;

use App\Models\DatoFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos fiscales elegidos para facturar una solicitud de servicio.
 * Espejo de pedido_facturacion. Ver la migración create_servicio_facturacion.
 *
 * Es la ELECCIÓN del cliente o del cajero, no la factura: la factura guarda
 * su propia copia (snapshot) en venta_cab al emitirse.
 */
class ServicioFacturacion extends Model
{
    public const TIPO_CI  = 'CI';
    public const TIPO_RUC = 'RUC';

    public const RECIBE_CORREO   = 'CORREO';
    public const RECIBE_WHATSAPP = 'WHATSAPP';

    protected $table      = 'servicio_facturacion';
    protected $primaryKey = 'sfa_id';

    const CREATED_AT = 'sfa_creado';
    const UPDATED_AT = 'sfa_actual';

    protected $fillable = [
        'sfa_sseid', 'sfa_tipdoc', 'sfa_dfiid', 'sfa_nrodoc', 'sfa_raznom',
        'sfa_telef', 'sfa_correo', 'sfa_recibe',
    ];

    protected $casts = [
        'sfa_id'     => 'integer',
        'sfa_sseid'  => 'integer',
        'sfa_dfiid'  => 'integer',
        'sfa_creado' => 'datetime',
        'sfa_actual' => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'sfa_sseid', 'sse_id');
    }

    public function datoFiscal(): BelongsTo
    {
        return $this->belongsTo(DatoFiscal::class, 'sfa_dfiid', 'dfi_id');
    }

    // ---------- Ayudas ----------

    public function esRuc(): bool
    {
        return $this->sfa_tipdoc === self::TIPO_RUC;
    }

    public function recibePorCorreo(): bool
    {
        return $this->sfa_recibe === self::RECIBE_CORREO;
    }
}

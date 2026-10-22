<?php

namespace App\Models\Servicio;

use App\Models\Compras\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gestión de una garantía de COMPRA con la empresa responsable del producto
 * (distribuidora/fabricante). Una por solicitud. El taller no repara: recibe el
 * equipo, lo envía a la empresa, y cuando vuelve registra el resultado.
 */
class GarantiaProveedor extends Model
{
    protected $table = 'garantia_proveedor';
    protected $primaryKey = 'gpr_id';

    const CREATED_AT = 'gpr_creado';
    const UPDATED_AT = 'gpr_actual';

    /** La empresa reparó el producto y lo devolvió. */
    public const REPARADO    = 'REPARADO';
    /** La empresa entregó otro producto del mismo modelo. */
    public const REEMPLAZADO = 'REEMPLAZADO';
    /** La empresa no aceptó la garantía (el producto vuelve sin cambios). */
    public const RECHAZADO   = 'RECHAZADO';

    public const RESULTADOS = [
        self::REPARADO    => 'Reparado por la empresa',
        self::REEMPLAZADO => 'Reemplazado por otro del mismo modelo',
        self::RECHAZADO   => 'Garantía no aceptada',
    ];

    protected $fillable = [
        'gpr_sseid', 'gpr_proid', 'gpr_nrocaso', 'gpr_contacto',
        'gpr_fecenv', 'gpr_fecrec', 'gpr_result', 'gpr_nroserr', 'gpr_obs', 'gpr_usuid',
    ];

    protected $casts = [
        'gpr_fecenv' => 'date',
        'gpr_fecrec' => 'date',
        'gpr_creado' => 'datetime',
        'gpr_actual' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'gpr_sseid', 'sse_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'gpr_proid', 'pro_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'gpr_usuid', 'usu_id');
    }

    public function resuelta(): bool
    {
        return $this->gpr_result !== null;
    }
}

<?php

namespace App\Models\Venta;

use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cheque declarado para pagar cuotas. Espejo reducido de PagoCheque.
 *
 * Reutiliza sus constantes (tipos, estados y plazo) a proposito: es el
 * mismo papel y el mismo ciclo, solo que contra una factura a credito en
 * vez de contra un pedido. Duplicar los codigos abriria la puerta a que
 * las dos tablas se desincronicen.
 */
class CuotaCheque extends Model
{
    protected $table      = 'cuota_cheque';
    protected $primaryKey = 'cch_id';
    public    $timestamps = false;

    const CREATED_AT = 'cch_creado';

    protected $fillable = [
        'cch_cpmid', 'cch_tipo', 'cch_banco', 'cch_nrodoc', 'cch_nombre',
        'cch_nro', 'cch_fecobr', 'cch_estado', 'cch_fevcto',
        'cch_frecep', 'cch_usurec', 'cch_mrpid', 'cch_motdet',
    ];

    protected $casts = [
        'cch_id'     => 'integer',
        'cch_cpmid'  => 'integer',
        'cch_fecobr' => 'date',
        'cch_fevcto' => 'datetime',
        'cch_frecep' => 'datetime',
        'cch_creado' => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function medio(): BelongsTo
    {
        return $this->belongsTo(CuotaPagoMedio::class, 'cch_cpmid', 'cpm_id');
    }

    public function usuarioReceptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cch_usurec', 'usu_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'cch_mrpid', 'mrp_id');
    }

    // ---------- Lectura ----------

    public function descripcionTipo(): string
    {
        return PagoCheque::DESCRIPCIONES[$this->cch_tipo] ?? $this->cch_tipo;
    }

    public function descripcionEstado(): string
    {
        return PagoCheque::ESTADOS[$this->cch_estado] ?? $this->cch_estado;
    }

    public function estaPendiente(): bool
    {
        return in_array($this->cch_estado, PagoCheque::PENDIENTES, true);
    }

    public function vencido(): bool
    {
        return $this->estaPendiente()
            && $this->cch_fevcto !== null
            && $this->cch_fevcto->isPast();
    }

    /** Mismo plazo que el cheque de un pedido. */
    public static function calcularVencimiento(): \Illuminate\Support\Carbon
    {
        return now()->addHours(PagoCheque::HORAS_PLAZO);
    }
}
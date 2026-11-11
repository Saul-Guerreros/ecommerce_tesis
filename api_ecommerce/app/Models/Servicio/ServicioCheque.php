<?php

namespace App\Models\Servicio;

use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cheque con el que se paga UN pago del servicio (espejo de PagoCheque).
 * Tipos, estados y plazo se toman de PagoCheque: el ciclo es el mismo en
 * pedidos y en servicio técnico, cambia sólo a qué pago cuelga.
 */
class ServicioCheque extends Model
{
    protected $table = 'servicio_cheque';
    protected $primaryKey = 'sch_id';
    public $timestamps = false;
    const CREATED_AT = 'sch_creado';

    protected $fillable = [
        'sch_spaid', 'sch_tipo', 'sch_banco', 'sch_nrodoc', 'sch_nombre', 'sch_nro', 'sch_fecobr',
        'sch_estado', 'sch_fevcto', 'sch_frecep', 'sch_usurec', 'sch_mrpid', 'sch_motdet',
    ];

    protected $casts = [
        'sch_fecobr' => 'date',
        'sch_fevcto' => 'datetime',
        'sch_frecep' => 'datetime',
        'sch_creado' => 'datetime',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(ServicioPago::class, 'sch_spaid', 'spa_id');
    }

    public function usuarioReceptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'sch_usurec', 'usu_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'sch_mrpid', 'mrp_id');
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(ServicioChequeCambio::class, 'scc_schid', 'sch_id')->orderByDesc('scc_fecha');
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('sch_estado', PagoCheque::PENDIENTES);
    }

    public function scopeVencidos($query)
    {
        return $query->pendientes()->whereNotNull('sch_fevcto')->where('sch_fevcto', '<', now());
    }

    public function esDiferido(): bool
    {
        return $this->sch_tipo === PagoCheque::TIPO_DIFERIDO;
    }

    public function estaPendiente(): bool
    {
        return in_array($this->sch_estado, PagoCheque::PENDIENTES, true);
    }

    /** El papel llegó. Sigue siendo true después de acreditarse. */
    public function fueRecibido(): bool
    {
        return in_array($this->sch_estado, PagoCheque::EN_PODER_TIENDA, true);
    }

    public function fueAcreditado(): bool
    {
        return $this->sch_estado === PagoCheque::ACREDITADO;
    }

    public function vencido(): bool
    {
        return $this->estaPendiente() && $this->sch_fevcto !== null && $this->sch_fevcto->isPast();
    }

    /** Banco, titular, CI y número; en el diferido, además la fecha de cobro. */
    public function datosCompletos(): bool
    {
        if (blank($this->sch_banco) || blank($this->sch_nombre)
            || blank($this->sch_nrodoc) || blank($this->sch_nro)) {
            return false;
        }

        return !$this->esDiferido() || $this->sch_fecobr !== null;
    }

    public static function calcularVencimiento(): Carbon
    {
        return now()->addHours(PagoCheque::HORAS_PLAZO);
    }

    public function descripcionTipo(): string
    {
        return PagoCheque::DESCRIPCIONES[$this->sch_tipo] ?? $this->sch_tipo;
    }

    public function descripcionEstado(): string
    {
        return PagoCheque::ESTADOS[$this->sch_estado] ?? $this->sch_estado;
    }
}
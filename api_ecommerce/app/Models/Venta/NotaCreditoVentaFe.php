<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de la declaracion electronica (SIFEN) de una Nota de Credito de venta.
 * Espejo de VentaFe; ver create_nota_credito_venta_fe_table.
 */
class NotaCreditoVentaFe extends Model
{
    public const ESTADO_PENDIENTE = 1;
    public const ESTADO_ENVIADO   = 2;   // aprobada por la DNIT
    public const ESTADO_RECHAZADO = 3;

    /**
     * Errores TRANSITORIOS: no dependen de un dato mal cargado sino de un
     * corte, una pausa o una demora. Reintentar con el MISMO numero puede
     * resolverlos solo (guia, capitulo 8).
     */
    public const ERRORES_REINTENTABLES = [
        'sin_respuesta',
        'dnit_sin_respuesta',
        'servicio_pausado',
        'entrega_pendiente',
        'respuesta_incompleta',
        'no_se_pudo_guardar',
        'http_202', 'http_429', 'http_500', 'http_502', 'http_503', 'http_504',
    ];

    protected $table      = 'nota_credito_venta_fe';
    protected $primaryKey = 'nfe_id';
    public    $timestamps = false;

    protected $fillable = [
        'nfe_ncvid', 'nfe_estado', 'nfe_error',
        'nfe_cdc', 'nfe_ambiente', 'nfe_codigo', 'nfe_mensaje', 'nfe_aviso', 'nfe_qr',
        'nfe_xml_ruta', 'nfe_pdf_ruta', 'nfe_confirmado',
        'nfe_correo_enviado', 'nfe_correo_detalle', 'nfe_correo_fecha',
        'nfe_intentos', 'nfe_ultimo_intento', 'nfe_aprobado', 'nfe_actualizado',
    ];

    protected $casts = [
        'nfe_id'             => 'integer',
        'nfe_ncvid'          => 'integer',
        'nfe_estado'         => 'integer',
        'nfe_intentos'       => 'integer',
        'nfe_confirmado'     => 'datetime',
        'nfe_ultimo_intento' => 'datetime',
        'nfe_aprobado'       => 'datetime',
        'nfe_creado'         => 'datetime',
        'nfe_actualizado'    => 'datetime',
        'nfe_correo_enviado' => 'boolean',
        'nfe_correo_fecha'   => 'datetime',
    ];

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCreditoVenta::class, 'nfe_ncvid', 'ncv_id');
    }

    public function estaPendiente(): bool
    {
        return $this->nfe_estado === self::ESTADO_PENDIENTE;
    }

    public function estaEnviado(): bool
    {
        return $this->nfe_estado === self::ESTADO_ENVIADO;
    }

    public function estaRechazado(): bool
    {
        return $this->nfe_estado === self::ESTADO_RECHAZADO;
    }

    public function esReintentable(): bool
    {
        return $this->estaPendiente()
            && in_array($this->nfe_error, self::ERRORES_REINTENTABLES, true);
    }

    /**
     * Mientras exista la posibilidad de que la DNIT ya tenga la NC, NO se
     * puede anular internamente: el sistema quedaria en desacuerdo con la
     * DNIT. La via correcta es un evento de cancelacion (168 h, guia 23.6).
     *
     * Solo se anula una NC RECHAZADA, o una PENDIENTE que nunca llego a la
     * DNIT (sin CDC y con un error que exige correccion manual).
     */
    public function bloqueaAnulacion(): bool
    {
        if ($this->estaRechazado()) {
            return false;
        }

        $nuncaLlego = $this->estaPendiente()
            && $this->nfe_cdc === null
            && $this->nfe_error !== null
            && !in_array($this->nfe_error, self::ERRORES_REINTENTABLES, true);

        return !$nuncaLlego;
    }

    public function scopeReintentables(Builder $query): Builder
    {
        return $query->where('nfe_estado', self::ESTADO_PENDIENTE)
            ->where(function (Builder $q) {
                $q->whereNull('nfe_error')
                  ->orWhereIn('nfe_error', self::ERRORES_REINTENTABLES);
            });
    }
}
<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de la declaracion electronica (SIFEN) de una factura de venta.
 * Ver la migracion create_venta_fe_table para el detalle de cada campo.
 */
class VentaFe extends Model
{
    public const ESTADO_PENDIENTE = 1;
    public const ESTADO_ENVIADO   = 2;   // aprobado por la DNIT
    public const ESTADO_RECHAZADO = 3;
    /** CDC y archivos provisionales listos; la aprobación sigue en segundo plano. */
    public const ESTADO_PREPARADO = 4;
        /** Envío pendiente retirado en el portal de la API (reactivable). */
    public const ESTADO_RETIRADO    = 5;
    /** Evento de cancelación aprobado: definitivo. */
    public const ESTADO_CANCELADO   = 6;
    /** Número inutilizado: definitivo, no se reactiva. */
    public const ESTADO_INUTILIZADO = 7;

    /**
     * Errores TRANSITORIOS: no dependen de un dato mal cargado, sino de
     * un corte, una pausa o una demora. Reintentar con el mismo numero
     * puede resolverlos solo.
     *
     * Todo lo que NO este aca (payload_invalido, datos_invalidos,
     * comprobante_invalido, contenido_distinto, sede_desconocida,
     * timbrado_*, emisor_*, token_invalido, llave_invalida...) exige que
     * una persona corrija algo: reintentar en bucle no lo arregla nunca.
     */
    public const ERRORES_REINTENTABLES = [
        'sin_respuesta',        // corte de red o tiempo agotado
        'dnit_sin_respuesta',   // 502: no se supo si llego a la DNIT
        'servicio_pausado',     // 503
        'respuesta_incompleta',
        'no_se_pudo_guardar',   // fallo local de disco; la API aun tiene los archivos
        'http_202', 'http_429', 'http_500', 'http_502', 'http_503', 'http_504',
    ];

    protected $table      = 'venta_fe';
    protected $primaryKey = 'vfe_id';
    public    $timestamps = false;

    protected $fillable = [
        'vfe_venid', 'vfe_estado', 'vfe_entinm', 'vfe_error',
        'vfe_cdc', 'vfe_ambiente', 'vfe_codigo', 'vfe_mensaje', 'vfe_aviso', 'vfe_qr',
        'vfe_xml_ruta', 'vfe_pdf_ruta', 'vfe_confirmado',
        'vfe_intentos', 'vfe_ultimo_intento', 'vfe_aprobado', 'vfe_actualizado',
        'vfe_correo_enviado', 'vfe_correo_detalle', 'vfe_correo_fecha',
        'vfe_correo_pend', 'vfe_correo_prog', 'vfe_correo_intentos',
    ];

    protected $casts = [
        'vfe_id'              => 'integer',
        'vfe_venid'           => 'integer',
        'vfe_estado'          => 'integer',
        'vfe_entinm'          => 'boolean',
        'vfe_intentos'        => 'integer',
        'vfe_confirmado'      => 'datetime',
        'vfe_ultimo_intento'  => 'datetime',
        'vfe_aprobado'        => 'datetime',
        'vfe_creado'          => 'datetime',
        'vfe_actualizado'     => 'datetime',
        'vfe_correo_enviado'  => 'boolean',
        'vfe_correo_fecha'    => 'datetime',
        'vfe_correo_pend'     => 'boolean',
        'vfe_correo_prog'     => 'boolean',
        'vfe_correo_intentos' => 'integer',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(VentaCab::class, 'vfe_venid', 'ven_id');
    }

    public function estaPendiente(): bool
    {
        return $this->vfe_estado === self::ESTADO_PENDIENTE;
    }

    public function estaEnviado(): bool
    {
        return $this->vfe_estado === self::ESTADO_ENVIADO;
    }
    
    public function estaPreparado(): bool
    {
        return $this->vfe_estado === self::ESTADO_PREPARADO;
    }

    /**
     * Hay documento para respaldar un cobro: CDC y archivos guardados, sea
     * aprobado o provisional (guía 6.6.A: un 202 sin archivos no alcanza).
     */
    public function tieneDocumento(): bool
    {
        return in_array($this->vfe_estado, [self::ESTADO_ENVIADO, self::ESTADO_PREPARADO], true)
            && $this->vfe_cdc !== null
            && $this->vfe_xml_ruta !== null
            && $this->vfe_pdf_ruta !== null;
    }

    public function estaRechazado(): bool
    {
        return $this->vfe_estado === self::ESTADO_RECHAZADO;
    }

        public function estaRetirado(): bool
    {
        return $this->vfe_estado === self::ESTADO_RETIRADO;
    }

    public function estaCancelado(): bool
    {
        return $this->vfe_estado === self::ESTADO_CANCELADO;
    }

    public function estaInutilizado(): bool
    {
        return $this->vfe_estado === self::ESTADO_INUTILIZADO;
    }

    /**
     * Documento sin validez aunque tenga archivos (guía 10.3): rechazado,
     * retirado, cancelado o inutilizado. No respalda un cobro.
     */
    public function sinValidez(): bool
    {
        return in_array($this->vfe_estado, [
            self::ESTADO_RECHAZADO, self::ESTADO_RETIRADO, self::ESTADO_CANCELADO, self::ESTADO_INUTILIZADO,
        ], true);
    }

    /** Pendiente por una causa transitoria: vale la pena reintentar sola. */
    public function esReintentable(): bool
    {
        return $this->estaPendiente()
            && in_array($this->vfe_error, self::ERRORES_REINTENTABLES, true);
    }

    /**
     * Mientras exista la posibilidad de que la DNIT ya tenga el comprobante
     * (aprobado, o pendiente con CDC, o pendiente por una causa transitoria),
     * la factura NO puede anularse internamente: el sistema quedaria en
     * desacuerdo con la DNIT. Se resuelve con una nota de credito.
     *
     * Si fue RECHAZADA, o si quedo PENDIENTE por un dato que hay que corregir
     * y nunca llego a la DNIT (sin CDC), se puede anular.
     */
    public function bloqueaAnulacion(): bool
    {
               // Sin validez ante la API: se anula localmente y se re-emite con número nuevo.
        if ($this->sinValidez()) {
            return false;
        }

        $nuncaLlego = $this->estaPendiente()
            && $this->vfe_cdc === null
            && $this->vfe_error !== null
            && !in_array($this->vfe_error, self::ERRORES_REINTENTABLES, true);

        return !$nuncaLlego;
    }

        /**
     * Lo que el barrido automatico (facturacion:reintentar) vuelve a encolar:
     *   - PENDIENTES nunca intentadas o de causa transitoria.
     *   - PREPARADAS: seguimiento de la aprobacion de la DNIT.
     */
    public function scopeReintentables(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $p) {
                $p->where('vfe_estado', self::ESTADO_PENDIENTE)
                  ->where(function (Builder $e) {
                      $e->whereNull('vfe_error')
                        ->orWhereIn('vfe_error', self::ERRORES_REINTENTABLES);
                  });
            })->orWhere('vfe_estado', self::ESTADO_PREPARADO);
        });
    }
}
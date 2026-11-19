<?php

namespace App\Models\Credito;

use App\Models\Cliente;
use App\Models\Configuracion\Ciudad;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Configuracion\Deposito;

use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Relations\HasOne;


class SolicitudCredito extends Model
{
    protected $table      = 'solicitud_credito';
    protected $primaryKey = 'scr_id';

    const CREATED_AT = 'scr_creado';
    const UPDATED_AT = 'scr_actual';

    public const VIVIENDA_PROPIA    = 'PROPIA';
    public const VIVIENDA_ALQUILER  = 'ALQUILER';

    /** Mismos valores que el CHECK de la migracion y que persona.per_estciv. */
    public const ESTADOS_CIVILES = ['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO', 'UNION_LIBRE'];

    public const TIPOS_VIVIENDA = [
        self::VIVIENDA_PROPIA,
        self::VIVIENDA_ALQUILER,
    ];

    /**
     * Motivos de rechazo. Clave = codigo guardado en scr_motrec (y
     * validado por ck_scr_motrec), valor = etiqueta que ve el usuario.
     * Un solo lugar del sistema define ambos.
     */
    public const MOTIVOS_RECHAZO = [
        'INGRESOS'   => 'Ingresos insuficientes',
        'ANTIGUEDAD' => 'Antigüedad laboral insuficiente',
        'DATOS'      => 'Datos incompletos o inconsistentes',
        'MONTO'      => 'Monto fuera de límite',
        'OTRO'       => 'Otro',
    ];

    /** Estados en los que el administrador todavia puede intervenir. */
    public const ESTADOS_ABIERTOS = [
        EstadoSolicitud::PENDIENTE,
        EstadoSolicitud::EN_REVISION,
    ];

    /**
     * Estados en los que la solicitud TIENE stock reservado.
     *
     * Es la misma lista que bloquea una segunda solicitud del mismo
     * cliente, y no por casualidad: el limite existe justamente para que
     * un cliente no acumule reservas. Una sola constante para las dos
     * reglas evita que se separen con el tiempo.
     *
     * ACEPTADA sigue reservando: la reserva se traspasa al pedido recien
     * cuando el checkout lo crea (etapa D).
     */
    public const ESTADOS_CON_RESERVA = [
        EstadoSolicitud::PENDIENTE,
        EstadoSolicitud::EN_REVISION,
        EstadoSolicitud::APROBADA,
        EstadoSolicitud::ACEPTADA,
    ];

    protected $fillable = [
        'scr_numero', 'scr_cliid', 'scr_usuid', 'scr_escid',
        'scr_cedula', 'scr_nombre', 'scr_apelli', 'scr_fechna', 'scr_celula', 'scr_email',
        'scr_ciuid', 'scr_barrio', 'scr_calle', 'scr_nrocasa', 'scr_referen',
        'scr_estciv', 'scr_tprid', 'scr_proid', 'scr_tipviv', 'scr_sertit', 'scr_relsti',
        'scr_empres', 'scr_dirlab', 'scr_tellab', 'scr_cargo', 'scr_ingres', 'scr_fecing',
        'scr_toncon', 'scr_recfin', 'scr_total', 'scr_impues', 'scr_plazo', 'scr_cuomen',
        'scr_declar', 'scr_observ',

        // ---------- Gestion del panel admin ----------
        'scr_fecpri', 'scr_vighas',
        'scr_motrec', 'scr_detrec', 'scr_fecdec', 'scr_usudec',
        'scr_fecace', 'scr_lugfir', 'scr_depid', 'scr_fecres',
    ];

    protected $casts = [
        'scr_cliid'  => 'integer',
        'scr_usuid'  => 'integer',
        'scr_escid'  => 'integer',
        'scr_ciuid'  => 'integer',
        'scr_tprid'  => 'integer',
        'scr_proid'  => 'integer',
        'scr_plazo'  => 'integer',
        'scr_fechna' => 'date',
        'scr_fecing' => 'date',
        'scr_ingres' => 'decimal:2',
        'scr_toncon' => 'decimal:2',
        'scr_recfin' => 'decimal:2',
        'scr_total'  => 'decimal:2',
        'scr_impues' => 'decimal:2',
        'scr_cuomen' => 'decimal:2',
        'scr_declar' => 'boolean',
        'scr_creado' => 'datetime',
        'scr_actual' => 'datetime',

        'scr_fecpri' => 'date',
        'scr_vighas' => 'date',
        'scr_fecdec' => 'datetime',
        'scr_usudec' => 'integer',
        'scr_fecace' => 'datetime',
        'scr_depid' => 'integer',
        'scr_fecres' => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'scr_cliid', 'cli_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'scr_usuid', 'usu_id');
    }

    /** Administrador que aprobo o rechazo la solicitud. */
    public function usuarioDecision(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'scr_usudec', 'usu_id');
    }

    /** Depósito donde quedó la reserva. NULL si nunca reservó. */
    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class, 'scr_depid', 'dpo_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoSolicitud::class, 'scr_escid', 'esc_id');
    }

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'scr_ciuid', 'ciu_id');
    }

    public function profesion(): BelongsTo
    {
        return $this->belongsTo(Profesion::class, 'scr_proid', 'pro_id');
    }

    /**
     * Relacion laboral declarada (Funcionario Publico / Privado /
     * Independiente). Es FK propia de la solicitud y NO se deduce de la
     * profesion: los dos catalogos son independientes.
     */
    public function tipoProfesion(): BelongsTo
    {
        return $this->belongsTo(TipoProfesion::class, 'scr_tprid', 'tpr_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SolicitudCreditoDetalle::class, 'scd_scrid', 'scr_id');
    }

    public function referencias(): HasMany
    {
        return $this->hasMany(SolicitudCreditoReferencia::class, 'scf_scrid', 'scr_id')
                    ->orderBy('scf_orden');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(SolicitudCreditoCuota::class, 'scc_scrid', 'scr_id')
                    ->orderBy('scc_numero');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(SolicitudCreditoHistorial::class, 'sch_scrid', 'scr_id')
                    ->orderBy('sch_fecha');
    }

    /** Pedido generado a partir de esta solicitud (existe solo en estado CONVERTIDA). */
    public function pedido(): HasOne
    {
        return $this->hasOne(Pedido::class, 'ped_scrid', 'scr_id');
    }

    // ---------- Derivados: identidad ----------

    /**
     * Numeracion correlativa a partir del id real, igual que
     * PedidoService hace con ped_numero. Se asigna despues del INSERT
     * (el id recien existe ahi) y dentro de la misma transaccion.
     */
    public static function numeroPara(int $id): string
    {
        return 'SC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function nombreCompleto(): string
    {
        return trim($this->scr_nombre . ' ' . $this->scr_apelli);
    }

    /** Edad al momento de solicitar, no la de hoy: es lo que se evaluo. */
    public function edad(): ?int
    {
        if (! $this->scr_fechna) {
            return null;
        }

        return Carbon::parse($this->scr_fechna)
            ->diffInYears($this->scr_creado ?? Carbon::now());
    }

    /**
     * Antiguedad laboral calculada, NO almacenada: guardarla seria
     * conservar un dato que envejece mal (hoy "2 anios 4 meses", en
     * seis meses ya seria falso).
     *
     * Se mide contra la fecha de la solicitud y no contra hoy, para que
     * el administrador vea la antiguedad que el cliente tenia cuando
     * declaro, que es lo que se evalua.
     */
    public function antiguedadLaboral(): array
    {
        if (!$this->scr_fecing) {
            return ['anios' => 0, 'meses' => 0, 'texto' => null];
        }

        $hasta = $this->scr_creado ?? Carbon::now();
        $desde = Carbon::parse($this->scr_fecing);

        if ($desde->greaterThan($hasta)) {
            return ['anios' => 0, 'meses' => 0, 'texto' => 'Menos de un mes'];
        }

        $diferencia = $desde->diff($hasta);

        return [
            'anios' => $diferencia->y,
            'meses' => $diferencia->m,
            'texto' => self::textoAntiguedad($diferencia->y, $diferencia->m),
        ];
    }

    public static function textoAntiguedad(int $anios, int $meses): string
    {
        $partes = [];

        if ($anios > 0) {
            $partes[] = $anios . ' ' . ($anios === 1 ? 'año' : 'años');
        }
        if ($meses > 0) {
            $partes[] = $meses . ' ' . ($meses === 1 ? 'mes' : 'meses');
        }

        return $partes ? implode(' ', $partes) : 'Menos de un mes';
    }

    /**
     * Los datos del servicio ANDE/ESSAP solo aplican con casa propia.
     * Misma regla que el CHECK ck_scr_vivienda de la migracion y que el
     * *ngIf del formulario: las tres capas dicen lo mismo.
     */
    public function declaraServicio(): bool
    {
        return $this->scr_tipviv === self::VIVIENDA_PROPIA;
    }

    // ---------- Derivados: cronograma y capacidad de pago ----------

    /**
     * Cronograma consolidado CALCULADO desde el detalle.
     *
     * Es la unica fuente del calculo en todo el sistema: el Service la
     * usa para persistir solicitud_credito_cuota al aprobar, y el
     * Resource la usa para mostrar la estimacion antes de aprobar. Si
     * estuviera duplicada, la pantalla y la tabla podrian mostrar
     * importes distintos.
     *
     * Con planes mixtos el mes m paga la suma de las lineas cuyo plan
     * llega hasta m o mas: por eso la cuota baja cuando una linea corta
     * termina de pagarse.
     *
     * La ULTIMA cuota de cada linea absorbe el redondeo, de modo que la
     * suma del cronograma cierra exactamente contra scd_subtot. Sin este
     * ajuste, 12 cuotas redondeadas pueden diferir del total en varios
     * guaranies.
     */
    public function cronogramaCalculado(?string $fechaPrimera = null): array
    {
        $detalles = $this->relationLoaded('detalles') ? $this->detalles : $this->detalles()->get();

        if ($detalles->isEmpty()) {
            return [];
        }

        $plazo  = (int) $detalles->max('scd_numcuo');
        $origen = $fechaPrimera ?? $this->scr_fecpri;
        $inicio = $origen ? Carbon::parse($origen) : null;

        $filas = [];

        for ($mes = 1; $mes <= $plazo; $mes++) {
            $importe   = 0.0;
            $componen  = [];

            foreach ($detalles as $detalle) {
                $cuotas = (int) $detalle->scd_numcuo;

                if ($cuotas < $mes) {
                    continue;
                }

                $cuotaLinea = round((float) $detalle->scd_moncuo * (float) $detalle->scd_cantid);

                $importe += ($mes === $cuotas)
                    ? round((float) $detalle->scd_subtot) - $cuotaLinea * ($cuotas - 1)
                    : $cuotaLinea;

                $componen[] = $detalle->scd_descri;
            }

            $filas[] = [
                'numero'      => $mes,
                'vencimiento' => $inicio
                    ? $inicio->copy()->addMonthsNoOverflow($mes - 1)->format('Y-m-d')
                    : null,
                'importe'     => round($importe),
                'composicion' => implode(' + ', $componen),
            ];
        }

        return $filas;
    }

    /**
     * Agrupa el cronograma en tramos de importe constante, que es como
     * lo lee un humano: "Gs. 892.500 (cuotas 1 a 3)" en vez de tres
     * filas iguales.
     */
    public function tramosCuota(): array
    {
        $tramos = [];

        foreach ($this->cronogramaCalculado() as $fila) {
            $ultimo = count($tramos) - 1;

            if ($ultimo >= 0 && $tramos[$ultimo]['importe'] === $fila['importe']) {
                $tramos[$ultimo]['hasta'] = $fila['numero'];
                continue;
            }

            $tramos[] = [
                'importe' => $fila['importe'],
                'desde'   => $fila['numero'],
                'hasta'   => $fila['numero'],
            ];
        }

        return $tramos;
    }

    public function cuotaMasAlta(): ?array
    {
        $tramos = $this->tramosCuota();

        return $tramos ? collect($tramos)->sortByDesc('importe')->first() : null;
    }

    public function cuotaMasBaja(): ?array
    {
        $tramos = $this->tramosCuota();

        return $tramos ? collect($tramos)->sortBy('importe')->first() : null;
    }

    /**
     * Relacion cuota / ingreso sobre la cuota MAS ALTA, no sobre un
     * promedio: lo que define el riesgo es el mes que mas le va a costar
     * pagar, no el mes promedio.
     */
    public function relacionCuotaIngreso(): ?float
    {
        $ingreso = (float) $this->scr_ingres;
        $cuota   = $this->cuotaMasAlta();

        if ($ingreso <= 0 || ! $cuota) {
            return null;
        }

        return round($cuota['importe'] * 100 / $ingreso, 1);
    }

    // ---------- Derivados: maquina de estados ----------

    /** El administrador todavia puede intervenir (editar plan, decidir). */
    public function estaAbierta(): bool
    {
        return in_array($this->estado?->esc_codigo, self::ESTADOS_ABIERTOS, true);
    }

    public function puedeTomarse(): bool
    {
        return $this->estado?->esc_codigo === EstadoSolicitud::PENDIENTE;
    }

       /**
     * La oferta aprobada ya venció sin que el cliente la acepte.
     * Vale HASTA EL FINAL del día de scr_vighas (mismo criterio que creditos:expirar).
     */
    public function ofertaVencida(): bool
    {
        return $this->estado?->esc_codigo === EstadoSolicitud::APROBADA
            && $this->scr_vighas !== null
            && $this->scr_fecace === null
            && Carbon::parse($this->scr_vighas)->endOfDay()->isPast();
    }

    /** El cliente todavía puede aceptar (o rechazar) la oferta. */
    public function ofertaVigente(): bool
    {
        return $this->estado?->esc_codigo === EstadoSolicitud::APROBADA
            && $this->scr_vighas !== null
            && $this->scr_fecace === null
            && ! Carbon::parse($this->scr_vighas)->endOfDay()->isPast();
    }

    /**
     * Plazo único del cliente: la vigencia que fijó el admin al aprobar.
     * Cubre las dos etapas: aceptar la oferta (APROBADA) y completar el
     * pedido (ACEPTADA). Aceptar NO extiende el plazo.
     */
    public function plazoVigente(): bool
    {
        return in_array($this->estado?->esc_codigo, [EstadoSolicitud::APROBADA, EstadoSolicitud::ACEPTADA], true)
            && $this->scr_vighas !== null
            && ! Carbon::parse($this->scr_vighas)->endOfDay()->isPast();
    }

    /** El plazo se cumplió sin que la solicitud llegue a pedido. */
    public function plazoVencido(): bool
    {
        return in_array($this->estado?->esc_codigo, [EstadoSolicitud::APROBADA, EstadoSolicitud::ACEPTADA], true)
            && $this->scr_vighas !== null
            && Carbon::parse($this->scr_vighas)->endOfDay()->isPast();
    }

    /** Aceptó las condiciones y todavía puede armar el pedido. */
    public function puedeContinuarCheckout(): bool
    {
        return $this->estado?->esc_codigo === EstadoSolicitud::ACEPTADA && $this->plazoVigente();
    }

    /** Días que quedan del plazo (0 = vence hoy). NULL si ya no corre. */
    public function diasRestantesOferta(): ?int
    {
        if (! $this->plazoVigente()) {
            return null;
        }

        return max(0, (int) Carbon::today()->diffInDays(Carbon::parse($this->scr_vighas), false));
    }

    /** Cantidad de días que el admin otorgó al aprobar (la que se muestra en la cabecera). */
    public function vigenciaDias(): ?int
    {
        if (! $this->scr_vighas || ! $this->scr_fecdec) {
            return null;
        }

        return max(0, (int) Carbon::parse($this->scr_fecdec)->startOfDay()
            ->diffInDays(Carbon::parse($this->scr_vighas)->startOfDay(), false));
    }

    public function etiquetaMotivoRechazo(): ?string
    {
        return $this->scr_motrec
            ? (self::MOTIVOS_RECHAZO[$this->scr_motrec] ?? $this->scr_motrec)
            : null;
    }

    /** Tiene unidades apartadas en stock ahora mismo. */
    public function tieneReserva(): bool
    {
        return $this->scr_fecres !== null;
    }

    /** La oferta vencida debe liberar su reserva (barrido creditos:expirar). */
    public function reservaLiberable(): bool
    {
        return $this->tieneReserva() && $this->ofertaVencida();
    }

}
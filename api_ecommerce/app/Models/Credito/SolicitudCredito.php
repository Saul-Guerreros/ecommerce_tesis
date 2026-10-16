<?php

namespace App\Models\Credito;

use App\Models\Cliente;
use App\Models\Configuracion\Ciudad;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $fillable = [
        'scr_numero', 'scr_cliid', 'scr_usuid', 'scr_escid',
        'scr_cedula', 'scr_nombre', 'scr_apelli', 'scr_fechna', 'scr_celula', 'scr_email',
        'scr_ciuid', 'scr_barrio', 'scr_calle', 'scr_nrocasa', 'scr_referen',
        'scr_estciv', 'scr_tprid', 'scr_proid', 'scr_tipviv', 'scr_sertit', 'scr_relsti',
        'scr_empres', 'scr_dirlab', 'scr_tellab', 'scr_cargo', 'scr_ingres', 'scr_fecing',
        'scr_toncon', 'scr_recfin', 'scr_total', 'scr_impues', 'scr_plazo', 'scr_cuomen',
        'scr_declar', 'scr_observ',
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

    // ---------- Derivados ----------

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
}
<?php

namespace App\Models\Credito;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Borrador del wizard de Solicitud de Credito. Equivalente de
 * PedidoTemporal, con un bloque jsonb por paso del encabezado.
 *
 * Los sub-pasos del instructivo (Datos Personales 1/3, 2/3 y 3/3) NO
 * tienen fila ni columna propia: viven dentro de stm_person. El wizard
 * los resuelve en el cliente y recien golpea al backend cuando el paso
 * completo queda cerrado. Guardar sub-paso por sub-paso triplicaria las
 * peticiones sin ganar nada: si el cliente abandona a mitad del Paso 1,
 * ese paso igual esta incompleto y hay que rehacerlo.
 */
class SolicitudTemporal extends Model
{
    protected $table      = 'solicitud_temporal';
    protected $primaryKey = 'stm_id';

    const CREATED_AT = 'stm_creado';
    const UPDATED_AT = 'stm_actual';

    public const PASO_PERSONAL     = 1;
    public const PASO_LABORAL      = 2;
    public const PASO_REFERENCIAS  = 3;
    public const PASO_CONFIRMACION = 4;

    protected $fillable = ['stm_usuid', 'stm_paso', 'stm_person', 'stm_labora', 'stm_refere'];

    protected $casts = [
        'stm_usuid'  => 'integer',
        'stm_paso'   => 'integer',
        'stm_person' => 'array',
        'stm_labora' => 'array',
        'stm_refere' => 'array',
        'stm_creado' => 'datetime',
        'stm_actual' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'stm_usuid', 'usu_id');
    }

    public function tienePersonal(): bool
    {
        return !empty($this->stm_person);
    }

    public function tieneLaboral(): bool
    {
        return !empty($this->stm_labora);
    }

    public function tieneReferencias(): bool
    {
        return !empty($this->stm_refere);
    }

    /**
     * Unico punto que decide si el borrador puede convertirse en
     * solicitud. El controlador y el servicio preguntan por aca y no
     * repiten la condicion cada uno por su lado.
     */
    public function completo(): bool
    {
        return $this->tienePersonal() && $this->tieneLaboral() && $this->tieneReferencias();
    }

    /**
     * Paso al que hay que mandar al cliente cuando vuelve a entrar al
     * wizard: el primero que le falta completar.
     */
    public function primerPasoPendiente(): int
    {
        if (!$this->tienePersonal()) {
            return self::PASO_PERSONAL;
        }
        if (!$this->tieneLaboral()) {
            return self::PASO_LABORAL;
        }
        if (!$this->tieneReferencias()) {
            return self::PASO_REFERENCIAS;
        }

        return self::PASO_CONFIRMACION;
    }
}
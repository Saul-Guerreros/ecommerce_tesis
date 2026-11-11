<?php

namespace App\Models\Pedido;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de los datos de un cheque ANTES de que el admin los pise con
 * [Descartar cheque] o [Editar].
 *
 * Es lo que permite contrastar después qué declaró el cliente en el
 * checkout contra qué papel llegó realmente a la sucursal.
 */
class PagoChequeCambio extends Model
{
    protected $table = 'pago_cheque_cambio';
    protected $primaryKey = 'pcc_id';
    public $timestamps = false;
    const CREATED_AT = 'pcc_fecha';

    public const DESCARTE = 'DESCARTE';
    public const EDICION  = 'EDICION';

    protected $fillable = [
        'pcc_pchid', 'pcc_tipo', 'pcc_tipche', 'pcc_banco', 'pcc_nrodoc',
        'pcc_nombre', 'pcc_nro', 'pcc_fecobr', 'pcc_motivo', 'pcc_usuid',
    ];

    protected $casts = [
        'pcc_fecobr' => 'date',
        'pcc_fecha'  => 'datetime',
    ];

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(PagoCheque::class, 'pcc_pchid', 'pch_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'pcc_usuid', 'usu_id');
    }

    /**
     * Registra el estado actual del cheque antes de modificarlo.
     *
     * Se llama SIEMPRE antes del update, nunca después: el objetivo es
     * guardar lo que se pierde, no lo que queda.
     */
    public static function registrar(
        PagoCheque $cheque,
        string $tipo,
        ?int $usuarioId,
        ?string $motivo = null,
    ): self {
        return self::create([
            'pcc_pchid'  => $cheque->pch_id,
            'pcc_tipo'   => $tipo,
            'pcc_tipche' => $cheque->pch_tipo,
            'pcc_banco'  => $cheque->pch_banco,
            'pcc_nrodoc' => $cheque->pch_nrodoc,
            'pcc_nombre' => $cheque->pch_nombre,
            'pcc_nro'    => $cheque->pch_nro,
            'pcc_fecobr' => $cheque->pch_fecobr,
            'pcc_motivo' => $motivo,
            'pcc_usuid'  => $usuarioId,
        ]);
    }
}
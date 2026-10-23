<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class ServicioHistorialEstado extends Model
{
    protected $table = 'servicio_historial_estado';
    protected $primaryKey = 'she_id';
    public $timestamps = false;

    public const SERVICIO    = 'SERVICIO';
    public const INGRESO     = 'INGRESO';
    public const EGRESO      = 'EGRESO';
    public const PAGO        = 'PAGO';
    public const PRESUPUESTO = 'PRESUPUESTO';
    public const NOTA        = 'NOTA';
    public const CONTACTO    = 'CONTACTO';

    /** Registrar contacto con el cliente (she_tipo CONTACTO). */
    public const MEDIOS     = ['TELEFONO', 'WHATSAPP', 'CORREO', 'PRESENCIAL'];
    public const RESULTADOS = ['CONTACTADO', 'NO_CONTESTA', 'PIDE_TIEMPO'];

    public const ETIQUETAS_MEDIO = [
        'TELEFONO' => 'Teléfono', 'WHATSAPP' => 'WhatsApp', 'CORREO' => 'Correo', 'PRESENCIAL' => 'Presencial',
    ];
    public const ETIQUETAS_RESULTADO = [
        'CONTACTADO' => 'Contactado', 'NO_CONTESTA' => 'No contesta', 'PIDE_TIEMPO' => 'Pide tiempo',
    ];

    protected $fillable = [
        'she_sseid', 'she_usuid', 'she_tipo', 'she_estant', 'she_estid', 'she_coment', 'she_ip',
        'she_medio', 'she_result',
    ];

    protected $casts = ['she_fecha' => 'datetime'];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudServicio::class, 'she_sseid', 'sse_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'she_usuid', 'usu_id');
    }
}

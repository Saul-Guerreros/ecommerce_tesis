<?php

namespace App\Models\Servicio;

use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\TipoEntrega;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class EntregaServicio extends Model
{
    protected $table = 'entrega_servicio';
    protected $primaryKey = 'ese_id';
    public $timestamps = false;

    protected $fillable = [
        'ese_sseid', 'ese_tenid', 'ese_sucid', 'ese_dirid',
        'ese_nrodoc', 'ese_nombre', 'ese_apelli', 'ese_telef', 'ese_titula',
        'ese_departa', 'ese_ciudad', 'ese_barrio', 'ese_calle', 'ese_nrocasa', 'ese_referencia',
        'ese_cosenv', 'ese_obs', 'ese_fecha', 'ese_usuid',
    ];

    protected $casts = [
        'ese_titula' => 'boolean',
        'ese_cosenv' => 'decimal:2',
        'ese_fecha'  => 'datetime',
    ];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudServicio::class, 'ese_sseid', 'sse_id');
    }

    public function tipoEntrega()
    {
        return $this->belongsTo(TipoEntrega::class, 'ese_tenid', 'ten_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'ese_sucid', 'suc_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ese_usuid', 'usu_id');
    }

    public function direccionTexto(): ?string
    {
        $partes = array_filter([$this->ese_calle, $this->ese_nrocasa, $this->ese_barrio, $this->ese_ciudad, $this->ese_departa]);

        return $partes ? implode(', ', $partes) : null;
    }
}

<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SolicitudAdjunto extends Model
{
    protected $table = 'solicitud_adjunto';
    protected $primaryKey = 'sad_id';
    public $timestamps = false;

    public const CLIENTE     = 'CLIENTE';
    public const INGRESO     = 'INGRESO';
    public const DIAGNOSTICO = 'DIAGNOSTICO';

    protected $fillable = ['sad_sseid', 'sad_archiv', 'sad_nombre', 'sad_tipo', 'sad_usuid'];

    protected $casts = ['sad_subido' => 'datetime'];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudServicio::class, 'sad_sseid', 'sse_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->sad_archiv);
    }
}

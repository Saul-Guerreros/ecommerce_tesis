<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Discount\Discount;

class Slider extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'slider';
    protected $primaryKey = 'sld_id';

   
    public $timestamps = false;

    const DELETED_AT = 'sld_eliminado';

    protected $fillable = [
        'sld_titulo',
        'sld_subtitulo',
        'sld_label',
        'sld_imagen',
        'sld_destino_tipo',
        'sld_destino_id',
        'sld_destino_valor',
        'sld_orden',
        'sld_tipo',
        'sld_posicion',
        'sld_estado',
        'sld_color',
    ];

    /** Destinos posibles del botón del banner. */
    public const DESTINOS = ['producto', 'categoria', 'marca', 'campania', 'busqueda', 'url'];
    public const DESTINOS_CON_ID = ['producto', 'categoria', 'marca', 'campania'];
    public const DESTINOS_CON_VALOR = ['busqueda', 'url'];

    protected $casts = [
        'sld_creado'    => 'datetime',
        'sld_eliminado' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Slider $slider) {
            date_default_timezone_set('America/Lima');
            $slider->sld_creado = Carbon::now();
        });
    }

   
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'sld_prdid', 'prd_id');
    }

        /**
     * URL de la tienda a la que lleva el banner, o null si no tiene enlace
     * (o si el destino ya no está disponible: producto despublicado, campaña vencida).
     */
    public function enlaceTienda(): ?string
    {
        $id = (int) $this->sld_destino_id;

        return match ($this->sld_destino_tipo) {
            'producto' => ($slug = PublicacionWeb::publicadas()->where('pub_prdid', $id)->value('pub_slug'))
                ? '/producto/' . $slug
                : null,
            'categoria' => '/productos-busqueda?categoria=' . $id,
            'marca'     => '/productos-busqueda?marca=' . $id,
            'campania'  => $this->campaniaVigente() ? '/productos-busqueda?campania=' . $id : null,
            'busqueda'  => '/productos-busqueda?search=' . rawurlencode((string) $this->sld_destino_valor),
            'url'       => $this->sld_destino_valor,
            default     => null,
        };
    }

    /** La campaña destino está activa y dentro de sus fechas. */
    public function campaniaVigente(): bool
    {
        $hoy = now()->toDateString();

        return Discount::where('id', $this->sld_destino_id)
            ->where('state', 1)
            ->whereDate('start_date', '<=', $hoy)
            ->whereDate('end_date', '>=', $hoy)
            ->exists();
    }
}

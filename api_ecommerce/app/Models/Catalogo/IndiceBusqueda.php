<?php

namespace App\Models\Catalogo;

use App\Models\PublicacionWeb;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del índice de búsqueda (una por publicación publicada). La
 * escribe SOLO IndiceBusquedaService; ibu_vector e ibu_texto_corto son
 * columnas generadas por PostgreSQL y nunca se asignan desde PHP.
 */
class IndiceBusqueda extends Model
{
    protected $table      = 'indice_busqueda';
    protected $primaryKey = 'ibu_pubid';
    public    $incrementing = false;
    public    $timestamps = false;

    protected $fillable = [
        'ibu_pubid', 'ibu_prdid', 'ibu_catid', 'ibu_catpadr', 'ibu_marid',
        'ibu_titulo', 'ibu_marca', 'ibu_categoria', 'ibu_ficha_texto', 'ibu_descripcion',
        'ibu_ficha', 'ibu_actualizado',
    ];

    protected $casts = [
        'ibu_pubid'       => 'integer',
        'ibu_prdid'       => 'integer',
        'ibu_catid'       => 'integer',
        'ibu_catpadr'     => 'integer',
        'ibu_marid'       => 'integer',
        'ibu_ficha'       => 'array',
        'ibu_actualizado' => 'datetime',
    ];

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(PublicacionWeb::class, 'ibu_pubid', 'pub_id');
    }
}

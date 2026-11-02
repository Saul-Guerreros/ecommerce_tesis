<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class PaginaInformativa extends Model
{
    protected $table      = 'pagina_informativa';
    protected $primaryKey = 'pgi_id';

    const CREATED_AT = null;
    const UPDATED_AT = 'pgi_actualizado';

    protected $fillable = ['pgi_titulo', 'pgi_contenido'];

    protected $casts = [
        'pgi_id'          => 'integer',
        'pgi_actualizado' => 'datetime',
    ];

    /** Orden fijo en que se listan en el admin y en el menú de la tienda. */
    public const ORDEN_SLUGS = [
        'como-comprar',
        'garantia',
        'condiciones-compra',
        'terminos-y-condiciones',
        'politica-privacidad',
    ];
}
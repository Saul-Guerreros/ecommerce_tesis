<?php

namespace App\Models\Comportamiento;

use Illuminate\Database\Eloquent\Model;

class Busqueda extends Model
{
    public const ORIGEN_TIENDA = 'TIENDA';
    public const ORIGEN_CHATBOT = 'CHATBOT';

    protected $table = 'busqueda';
    protected $primaryKey = 'bus_id';

    const CREATED_AT = 'bus_creado';
    const UPDATED_AT = null;

    protected $fillable = [
        'bus_termino', 'bus_termnorm', 'bus_total', 'bus_modo',
        'bus_origen', 'bus_usuid', 'bus_visita',
    ];

    protected $casts = [
        'bus_total'  => 'integer',
        'bus_creado' => 'datetime',
    ];
}
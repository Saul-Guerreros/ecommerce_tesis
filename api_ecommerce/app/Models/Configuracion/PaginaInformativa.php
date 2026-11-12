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

    /** Catálogo fijo: slug => título inicial. */
    public const CATALOGO = [
        'como-comprar'           => 'Cómo comprar',
        'garantia'               => 'Plazos y condiciones de Garantía',
        'condiciones-compra'     => 'Cómo funciona tu compra',
        'terminos-y-condiciones' => 'Términos y Condiciones',
        'politica-privacidad'    => 'Política de Privacidad',
    ];

    /**
     * Crea las páginas del catálogo que no existan en la base de datos.
     * Es idempotente: nunca modifica ni sobrescribe una página existente,
     * por lo que no pisa el contenido ya editado desde el admin.
     */
    public static function asegurarCatalogo(): void
    {
        $existentes = static::query()->pluck('pgi_slug')->all();

        foreach (self::CATALOGO as $slug => $titulo) {
            if (in_array($slug, $existentes, true)) {
                continue;
            }

            // forceCreate: pgi_slug no es fillable (el admin no debe poder cambiarlo).
            static::forceCreate([
                'pgi_slug'      => $slug,
                'pgi_titulo'    => $titulo,
                'pgi_contenido' => '<p>Contenido pendiente de redacción. Editalo desde el panel admin → Atención al Cliente.</p>',
            ]);
        }
    }
}
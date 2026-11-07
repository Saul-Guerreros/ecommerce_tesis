<?php

namespace Database\Seeders;

use App\Models\Configuracion\DatosContacto;
use App\Models\Configuracion\PaginaNosotros;
use Illuminate\Database\Seeder;

/**
 * Contenido inicial de "Sobre nosotros" y "Contacto".
 * Solo crea la fila si no existe: no pisa lo editado desde el admin.
 *
 *   php artisan db:seed --class=PaginaInstitucionalSeeder
 */
class PaginaInstitucionalSeeder extends Seeder
{
    public function run(): void
    {
        if (! PaginaNosotros::query()->exists()) {
            PaginaNosotros::create([
                'pno_titulo'    => 'Sobre nosotros',
                'pno_subtitulo' => 'Tecnología y servicio técnico en Capiatá.',
                'pno_historia'  => '[Completar desde el panel admin → Sitio web → Sobre nosotros.]',
                'pno_mision'    => '[Completar misión.]',
                'pno_vision'    => '[Completar visión.]',
                'pno_valores'   => [
                    ['icono' => 'happy',     'titulo' => 'Atención cercana', 'descripcion' => '[Completar]'],
                    ['icono' => 'check',     'titulo' => 'Garantía real',    'descripcion' => '[Completar]'],
                    ['icono' => 'lifesaver', 'titulo' => 'Soporte técnico',  'descripcion' => '[Completar]'],
                ],
                'pno_cifras'    => [],
                'pno_publicado' => false, // se publica desde el admin cuando el contenido esté listo
            ]);
        }

        if (! DatosContacto::query()->exists()) {
            DatosContacto::create([
                'dco_titulo'    => 'Contacto',
                'dco_intro'     => '¿Tenés dudas sobre un producto, tu pedido o el servicio técnico? Escribinos.',
                'dco_direccion' => '[Dirección], Capiatá, Paraguay',
                'dco_horario'   => "Lunes a viernes: 8:00 a 18:00\nSábados: 8:00 a 12:00",
                'dco_mapa_zoom' => 16,
                'dco_publicado' => false,
            ]);
        }
    }
}
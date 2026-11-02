<?php

namespace Database\Seeders;

use App\Models\Configuracion\PaginaInformativa;
use Illuminate\Database\Seeder;

class PaginaInformativaSeeder extends Seeder
{
    public function run(): void
    {
        $paginas = [
            [
                'pgi_slug' => 'como-comprar',
                'pgi_titulo' => 'Cómo comprar',
                'pgi_contenido' => '<p>Editá este contenido desde el panel admin → Configuración → Atención al Cliente.</p>',
            ],
            [
                'pgi_slug' => 'garantia',
                'pgi_titulo' => 'Plazos y condiciones de Garantía',
                'pgi_contenido' => '<p>Editá este contenido desde el panel admin → Configuración → Atención al Cliente.</p>',
            ],
            [
                'pgi_slug' => 'terminos-y-condiciones',
                'pgi_titulo' => 'Términos y Condiciones',
                // Pegá acá el contenido ACTUAL de terminos.component.html
                // (desde el <h1> hacia adentro), reemplazando:
                //   {{ tienda.nombreComercial }} -> {{EMPRESA}}
                //   {{ tienda.razonSocial }}     -> {{RAZON_SOCIAL}}
                //   {{ tienda.ruc }}             -> {{RUC}}
                //   {{ tienda.direccion }}       -> {{DIRECCION}}
                //   {{ tienda.email }}           -> {{EMAIL}}
                //   {{ tienda.telefono }}        -> {{TELEFONO}}
                //   {{ tienda.horario }}         -> {{HORARIO}}
                'pgi_contenido' => '<h1>Términos y Condiciones</h1><p>Al registrarte o comprar en {{EMPRESA}} aceptás estos Términos y Condiciones...</p>',
            ],
            [
                'pgi_slug' => 'politica-privacidad',
                'pgi_titulo' => 'Política de Privacidad',
                // Mismo criterio: pegá el contenido actual de privacidad.component.html
                'pgi_contenido' => '<h1>Política de Privacidad</h1><p>En {{EMPRESA}} protegemos tus datos personales...</p>',
            ],

            [
                'pgi_slug' => 'condiciones-compra',
                'pgi_titulo' => 'Cómo funciona tu compra',
                'pgi_contenido' => <<<'HTML'
                <h2>1. Reserva de stock</h2>
                <p>Al presionar “Confirmar pedido” se registra tu pedido con un número de seguimiento y reservamos tu stock por <strong>24 horas</strong>. El pedido queda en estado “pendiente de pago”. Si en ese plazo no se registra el pago, el pedido se cancela automáticamente y el stock se libera.</p>
                <h2>2. Pago</h2>
                <p>Podés pagar por transferencia bancaria, billetera móvil o en efectivo (contra entrega o al retirar en sucursal). Para transferencias y billetera móvil debés adjuntar el comprobante dentro del plazo de reserva. Tu pedido se confirma cuando verificamos el pago.</p>
                <h2>3. Entrega</h2>
                <p>Podés retirar en sucursal o recibir tu pedido en tu domicilio. El costo de envío se muestra antes de confirmar. [Completar: plazos de entrega.]</p>
                <h2>4. Cambios y devoluciones</h2>
                <p>[Completar: condiciones y plazos de cambios y devoluciones.]</p>
                <h2>5. Garantía</h2>
                <p>[Completar: resumen de la garantía.] El detalle está en <a href="/garantia" target="_blank" rel="noopener">Plazos y condiciones de Garantía</a>.</p>
                HTML,
             ],

        ];

        foreach ($paginas as $pagina) {
            PaginaInformativa::firstOrCreate(['pgi_slug' => $pagina['pgi_slug']], $pagina);
        }
    }
}
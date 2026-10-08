<?php

namespace Database\Seeders;

use App\Models\Chat\FaqTienda;
use Illuminate\Database\Seeder;

/**
 * Información de la tienda que el chatbot afirma textualmente.
 * IMPORTANTE: solo datos reales y vigentes de Z Informática.
 */
class FaqTiendaSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['horarios', '¿Cuál es el horario de atención?', '[Dias Lunea a Viernes desde las 08:00Hs hasta las 18:00Hs]'],
            ['retiro',   '¿Dónde queda la tienda?',          '[COMPLETAR: dirección en Capiatá y referencia]'],
            ['envios',   '¿Hacen envíos?',                   '[COMPLETAR: zonas, costo y plazo]'],
            ['pagos',    '¿Qué formas de pago aceptan?',      'Transferencia bancaria, billetera móvil (Tigo Money) y efectivo contra entrega.'],
            ['garantia', '¿Los productos tienen garantía?',   '[COMPLETAR: plazo y condiciones]'],
            ['general',  '¿Puedo cambiar un producto?',        '[COMPLETAR: política de cambios]'],
        ];

        foreach ($faqs as $orden => [$tema, $pregunta, $respuesta]) {
            FaqTienda::updateOrCreate(
                ['fqt_tema' => $tema, 'fqt_pregunta' => $pregunta],
                ['fqt_respuesta' => $respuesta, 'fqt_orden' => $orden, 'fqt_activo' => true],
            );
        }
    }
}
<?php

namespace Database\Seeders;

use App\Models\product\Atributo;
use App\Models\product\Propiedad;
use Illuminate\Database\Seeder;

/**
 * Datos referenciales: atributos técnicos base para las Plantillas de
 * Ficha Técnica de una tienda de informática. Es idempotente: si el
 * atributo ya existe (por nombre, incluso eliminado) no lo duplica, así
 * no choca con atributos ya creados a mano (ej. "Color" de variaciones).
 *
 * Criterio de tipo:
 *  - Número (2) con unidad: lo que el cliente compara con "más de / menos de"
 *    (RAM, almacenamiento, pantalla) -> el LLM puede filtrar por esp_valnum.
 *  - Seleccionable (3/4): valores de una lista corta y conocida.
 *  - Texto (1): valores muy variados (modelo de procesador o de GPU).
 *
 * Ejecutar: php artisan db:seed --class=AtributoFichaTecnicaSeeder
 */
class AtributoFichaTecnicaSeeder extends Seeder
{
    private const TEXTO = 1;
    private const NUMERO = 2;
    private const SELECCIONABLE = 3;
    private const MULTIPLE = 4;

    public function run(): void
    {
        $atributos = [
            // Notebooks / PC
            ['Procesador', self::TEXTO, null, []],
            ['Memoria RAM', self::NUMERO, 'GB', []],
            ['Almacenamiento', self::NUMERO, 'GB', []],
            ['Tipo de almacenamiento', self::SELECCIONABLE, null, ['SSD', 'HDD', 'eMMC']],
            ['Tarjeta de video', self::TEXTO, null, []],
            ['Tamaño de pantalla', self::NUMERO, 'pulgadas', []],
            ['Sistema operativo', self::SELECCIONABLE, null, ['Windows 11', 'Linux', 'macOS', 'Sin sistema operativo']],

            // Periféricos
            ['Conexión', self::MULTIPLE, null, ['USB', 'Bluetooth', 'Inalámbrico 2.4 GHz']],
            ['DPI', self::NUMERO, 'DPI', []],

            // Impresión
            ['Código de cartucho', self::TEXTO, null, []],
            ['Impresoras compatibles', self::MULTIPLE, null, []], // los modelos los carga el admin
            ['Tipo de cartucho', self::SELECCIONABLE, null, ['Original', 'Compatible', 'Recargable']],
            ['Color de tinta', self::SELECCIONABLE, null, ['Negro', 'Tricolor', 'Cian', 'Magenta', 'Amarillo']],
            ['Rendimiento', self::NUMERO, 'páginas', []],
        ];

        foreach ($atributos as [$nombre, $tipo, $unidad, $propiedades]) {
            $atributo = Atributo::withTrashed()->firstOrCreate(
                ['atb_nombre' => $nombre],
                ['atb_tipo' => $tipo, 'atb_unidad' => $unidad, 'atb_estado' => 1]
            );

            foreach ($propiedades as $propiedad) {
                Propiedad::withTrashed()->firstOrCreate(
                    ['prp_atbid' => $atributo->atb_id, 'prp_nombre' => $propiedad],
                    ['prp_codigo' => '']
                );
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

/**
 * Categorías raíz + subcategorías para el rubro de Z Informática (hardware
 * de computación). Los valores de 'icono' son nombres de íconos de UIkit
 * (https://getuikit.com/docs/icon) y coinciden con las opciones del
 * <select> de create-categorie/edit-categorie en el admin.
 *
 * Idempotente: usa updateOrCreate por (cat_nombre, cat_idpadr), así que se
 * puede correr las veces que quieras sin duplicar filas.
 */
class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $arbol = [
            [
                'nombre' => 'Notebooks & Tablets',
                'icono' => 'tablet',
                'hijas' => ['Notebooks', 'Tablets', 'Accesorios para notebook'],
            ],
            [
                'nombre' => 'Computadoras de Escritorio',
                'icono' => 'desktop',
                'hijas' => [
                    'PCs armadas',
                    'Placas madre',
                    'Procesadores',
                    'Memorias RAM',
                    'Discos y SSD',
                    'Fuentes de poder',
                    'Gabinetes',
                ],
            ],
            [
                'nombre' => 'Periféricos & Monitores',
                'icono' => 'cog',
                'hijas' => ['Monitores', 'Teclados', 'Mouses', 'Auriculares y parlantes', 'Impresoras'],
            ],
            [
                'nombre' => 'Redes & Conectividad',
                'icono' => 'world',
                'hijas' => ['Routers', 'Switches', 'Cables de red', 'Adaptadores Wi-Fi'],
            ],
            [
                'nombre' => 'Accesorios & Insumos',
                'icono' => 'bag',
                'hijas' => ['Mochilas y fundas', 'Pendrives y discos externos', 'Cartuchos y toner', 'Cables varios'],
            ],
        ];

        foreach ($arbol as $raiz) {
            $categoriaRaiz = Categoria::updateOrCreate(
                ['cat_nombre' => $raiz['nombre'], 'cat_idpadr' => null],
                ['cat_nivel' => 1, 'cat_icono' => $raiz['icono']]
            );

            foreach ($raiz['hijas'] as $nombreHija) {
                Categoria::updateOrCreate(
                    ['cat_nombre' => $nombreHija, 'cat_idpadr' => $categoriaRaiz->cat_id],
                    ['cat_nivel' => 2, 'cat_icono' => null]
                );
            }
        }
    }
}
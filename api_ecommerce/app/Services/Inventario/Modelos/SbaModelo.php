<?php
// app/Services/Inventario/Modelos/SbaModelo.php
namespace App\Services\Inventario\Modelos;

/**
 * Aproximación de Syntetos y Boylan (2005): Croston con corrección de sesgo.
 * Croston tiende a sobreestimar; SBA multiplica el pronóstico por (1 - α/2).
 */
class SbaModelo extends CrostonModelo
{
    public function nombre(): string
    {
        return 'sba';
    }

    protected function correccion(float $alfa): float
    {
        return 1 - $alfa / 2;
    }
}
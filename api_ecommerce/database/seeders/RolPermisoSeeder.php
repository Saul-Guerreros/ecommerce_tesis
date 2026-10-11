<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Roles: Administrador, Vendedor, Cliente.
 * - Administrador: acceso total (el middleware CheckPermission ademas le da
 *   bypass automatico, pero igual se le asignan todos los permisos aqui
 *   para que quede reflejado en det_rol_permiso).
 * - Vendedor: acceso al dashboard, pero SIN los permisos marcados como
 *   "solo administracion" mas abajo (gestion de usuarios/roles y catalogos
 *   sensibles). Ajusta la lista $permisosVetadosParaVendedor a gusto.
 * - Cliente: sin permisos de dashboard (usa el ecommerce, no el panel).
 */
class RolPermisoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Administrador', 'Vendedor', 'Cliente'];
        foreach ($roles as $descripcion) {
            Rol::updateOrCreate(['rol_descri' => $descripcion], ['rol_estado' => 1]);
        }

        // Convencion "modulo.accion". Agrega/edita libremente segun tus pantallas.
        $permisos = [
            'dashboard.ver',

            'productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar',
            'categorias.ver', 'categorias.gestionar',
            'marcas.ver', 'marcas.gestionar',
            'sliders.ver', 'sliders.gestionar',
            'atributos.ver', 'atributos.gestionar',
            'chatbot.gestionar',

            'clientes.ver',
            'reportes.ver',

            'usuarios.ver', 'usuarios.gestionar',
            'roles.gestionar',

            'pedidos.ver',
            'pedidos.exportar',
            'pedidos.gestionar',
            'pedidos.pago.verificar',   // se usa en la siguiente etapa
        ];

        foreach ($permisos as $descripcion) {
            Permiso::updateOrCreate(['prm_descri' => $descripcion], ['prm_estado' => 1]);
        }

        // Permisos que el Vendedor NO tiene (opciones deshabilitadas en su dashboard)
        $permisosVetadosParaVendedor = [
            'usuarios.ver', 'usuarios.gestionar',
            'roles.gestionar',
            'chatbot.gestionar',
            'pedidos.pago.verificar',
        ];

        $administrador = Rol::where('rol_descri', 'Administrador')->first();
        $vendedor = Rol::where('rol_descri', 'Vendedor')->first();

        $administrador->permisos()->sync(Permiso::pluck('prm_id'));

        $vendedor->permisos()->sync(
            Permiso::whereNotIn('prm_descri', $permisosVetadosParaVendedor)->pluck('prm_id')
        );

        // Cliente: sin permisos de dashboard (no se le asigna ninguno).
    }
}

<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Combos del componente de filtros reutilizable (sucursal, depósito,
 * agrupación). Respuesta con el formato de "config" del estándar.
 */
class ReporteConfigController extends Controller
{
    /**
     * GET /api/admin/reportes/config
     */
    public function config(): JsonResponse
    {
        $sucursales = DB::table('sucursal')
            ->where('suc_estado', 1)
            ->orderByDesc('suc_princi')
            ->orderBy('suc_nombre')
            ->get(['suc_id as id', 'suc_nombre as nombre', 'suc_princi as principal'])
            ->map(fn ($s) => [
                'id'        => (int) $s->id,
                'nombre'    => $s->nombre,
                'principal' => (bool) $s->principal,
            ]);

        $depositos = DB::table('deposito')
            ->where('dpo_estado', 1)
            ->orderBy('dpo_nombre')
            ->get(['dpo_id as id', 'dpo_nombre as nombre', 'dpo_sucid as sucursal_id'])
            ->map(fn ($d) => [
                'id'          => (int) $d->id,
                'nombre'      => $d->nombre,
                'sucursal_id' => (int) $d->sucursal_id,
            ]);

        $categorias = DB::table('categoria')
            ->orderBy('cat_nivel')
            ->orderBy('cat_nombre')
            ->get(['cat_id as id', 'cat_nombre as nombre', 'cat_idpadr as padre_id', 'cat_nivel as nivel'])
            ->map(fn ($c) => [
                'id'       => (int) $c->id,
                'nombre'   => $c->nombre,
                'padre_id' => $c->padre_id ? (int) $c->padre_id : null,
                'nivel'    => (int) $c->nivel,
            ]);

        $marcas = DB::table('marca')
            ->where('mar_estado', 1)
            ->orderBy('mar_descri')
            ->get(['mar_id as id', 'mar_descri as nombre'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'nombre' => $m->nombre]);   

        $proveedores = DB::table('proveedor')
            ->orderBy('pro_razon')
            ->get(['pro_id as id', 'pro_razon as nombre'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre]);
    
        $tiposDispositivo = DB::table('tipo_dispositivo')
            ->where('tdi_estado', 1)
            ->orderBy('tdi_descri')
            ->get(['tdi_id as id', 'tdi_descri as nombre'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre]);
    
        $tiposCliente = DB::table('tipo_cliente')
            ->where('tpc_estado', 1)
            ->orderBy('tpc_descri')
            ->get(['tpc_id as id', 'tpc_descri as nombre'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre]);
    
        return response()->json([
            'sucursales'   => $sucursales,
            'depositos'    => $depositos,
            'categorias'   => $categorias,
            'marcas'       => $marcas,
            'proveedores'  => $proveedores,
            'tipos_dispositivo' => $tiposDispositivo,
            'tipos_cliente' => $tiposCliente,
            'agrupaciones' => [
                ['valor' => 'dia',  'etiqueta' => 'Día'],
                ['valor' => 'mes',  'etiqueta' => 'Mes'],
                ['valor' => 'anio', 'etiqueta' => 'Año'],
            ],
            'periodo_defecto' => [
                'desde' => now()->startOfMonth()->toDateString(),
                'hasta' => now()->toDateString(),
            ],
        ]);
    }
}
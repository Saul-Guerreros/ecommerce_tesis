<?php

namespace App\Http\Controllers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Product\Atributo;
use App\Models\Product\Propiedad;
use App\Models\Product\VariacionProducto;

/**
 * Diseño elegido: cada fila de variacion_producto = una combinación completa (SKU),
 * ligada a N propiedades (una por atributo) vía la tabla pivote variacion_valor.
 * No se usa var_padrid (queda reservado a futuro / siempre NULL).
 *
 * Regla de consistencia: todas las variaciones de un mismo producto deben usar
 * exactamente el mismo conjunto de atributos (ej: si la primera variación usa
 * Color+Talla, todas las demás también deben traer Color+Talla).
 */
class VariacionProductoController extends Controller
{
    /**
     * GET /admin/variaciones/config
     * Atributos utilizables para variaciones: solo tipo 3 (Seleccionable).
     * Los tipos Texto/Número/Multiselección no aplican porque variacion_producto
     * no tiene columna de valor libre, solo referencia a propiedades vía pivote.
     */
    public function config()
    {
        $atributos = Atributo::with('propiedades')
            ->where('atb_estado', 1)
            ->where('atb_tipo', 3)
            ->orderBy('atb_id', 'desc')
            ->get();

        return response()->json([
            'atributos_variaciones' => $atributos->map(fn($a) => [
                'atb_id' => $a->atb_id,
                'atb_nombre' => $a->atb_nombre,
                'propiedades' => $a->propiedades->map(fn($p) => [
                    'prp_id' => $p->prp_id,
                    'prp_nombre' => $p->prp_nombre,
                ]),
            ]),
        ]);
    }

    /**
     * GET /admin/variaciones?producto_id=1
     */
    public function index(Request $request)
    {
        $request->validate(['producto_id' => 'required|integer']);

        $variaciones = VariacionProducto::with('propiedades.atributo')
            ->where('var_prdid', $request->producto_id)
            ->orderBy('var_proid', 'desc')
            ->get();

        return response()->json([
            'variaciones' => $variaciones->map(fn($v) => $this->mapVariacion($v)),
        ]);
    }

    /**
     * POST /admin/variaciones
     * Body: producto_id, propiedad_ids: number[] (una propiedad por atributo),
     *       precio_adicional, stock, stock_minimo, estado
     */
    public function store(Request $request)
    {
        $request->validate([
            'producto_id'      => 'required|integer|exists:producto,prd_id',
            'propiedad_ids'    => 'required|array|min:1',
            'propiedad_ids.*'  => 'integer|exists:propiedad,prp_id',
            'precio_adicional' => 'required|numeric|min:0',
            'stock'            => 'required|integer|min:0',
            'stock_minimo'     => 'required|integer|min:0',
            'estado'           => 'required|boolean',
        ]);

        $noRepetido = $this->validarSinAtributosRepetidos($request->propiedad_ids);
        if ($noRepetido) {
            return $noRepetido;
        }

        $consistencia = $this->validarConsistenciaAtributos($request->producto_id, $request->propiedad_ids);
        if ($consistencia) {
            return $consistencia;
        }

        $duplicado = $this->buscarCombinacionDuplicada($request->producto_id, $request->propiedad_ids);
        if ($duplicado) {
            return response()->json([
                'message' => 403,
                'message_text' => 'YA EXISTE UNA VARIACIÓN CON ESA MISMA COMBINACIÓN',
            ]);
        }

        $variacion = DB::transaction(function () use ($request) {
            $variacion = VariacionProducto::create([
                'var_prdid'  => $request->producto_id,
                'var_padrid' => null,
                'var_preadi' => $request->precio_adicional,
                'var_stock'  => $request->stock,
                'var_stkmin' => $request->stock_minimo,
                'var_estado' => $request->estado,
            ]);

            $variacion->propiedades()->sync($request->propiedad_ids);

            return $variacion;
        });

        return response()->json([
            'message' => 200,
            'variacion' => $this->mapVariacion($variacion->load('propiedades.atributo')),
        ]);
    }

    public function show(string $id)
    {
        $variacion = VariacionProducto::with('propiedades.atributo')->findOrFail($id);
        return response()->json(['variacion' => $this->mapVariacion($variacion)]);
    }

    /**
     * PUT /admin/variaciones/{id}
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'producto_id'      => 'required|integer|exists:producto,prd_id',
            'propiedad_ids'    => 'required|array|min:1',
            'propiedad_ids.*'  => 'integer|exists:propiedad,prp_id',
            'precio_adicional' => 'required|numeric|min:0',
            'stock'            => 'required|integer|min:0',
            'stock_minimo'     => 'required|integer|min:0',
            'estado'           => 'required|boolean',
        ]);

        $noRepetido = $this->validarSinAtributosRepetidos($request->propiedad_ids);
        if ($noRepetido) {
            return $noRepetido;
        }

        $consistencia = $this->validarConsistenciaAtributos($request->producto_id, $request->propiedad_ids, $id);
        if ($consistencia) {
            return $consistencia;
        }

        $duplicado = $this->buscarCombinacionDuplicada($request->producto_id, $request->propiedad_ids, $id);
        if ($duplicado) {
            return response()->json([
                'message' => 403,
                'message_text' => 'YA EXISTE UNA VARIACIÓN CON ESA MISMA COMBINACIÓN',
            ]);
        }

        $variacion = VariacionProducto::findOrFail($id);

        DB::transaction(function () use ($request, $variacion) {
            $variacion->update([
                'var_preadi' => $request->precio_adicional,
                'var_stock'  => $request->stock,
                'var_stkmin' => $request->stock_minimo,
                'var_estado' => $request->estado,
            ]);
            $variacion->propiedades()->sync($request->propiedad_ids);
        });

        return response()->json([
            'message' => 200,
            'variacion' => $this->mapVariacion($variacion->load('propiedades.atributo')),
        ]);
    }

    /**
     * DELETE /admin/variaciones/{id}
     * Las FK de variacion_valor están en ON DELETE NO ACTION, así que hay que
     * limpiar el pivote antes de borrar la variación o la BD rechazará el delete.
     */
    public function destroy(string $id)
    {
        // TODO: validar que la variación no esté referenciada en carrito/pedidos antes de eliminar.
        $variacion = VariacionProducto::findOrFail($id);

        DB::transaction(function () use ($variacion) {
            $variacion->propiedades()->detach();
            $variacion->delete();
        });

        return response()->json(['message' => 200]);
    }

    /**
     * No se puede seleccionar más de una propiedad del mismo atributo dentro
     * de una misma combinación (ej: no puede ser Rojo Y Azul a la vez).
     */
    private function validarSinAtributosRepetidos(array $propiedad_ids)
    {
        $atributo_ids = Propiedad::whereIn('prp_id', $propiedad_ids)->pluck('prp_atbid');

        if ($atributo_ids->count() !== $atributo_ids->unique()->count()) {
            return response()->json([
                'message' => 403,
                'message_text' => 'NO PUEDES SELECCIONAR DOS PROPIEDADES DEL MISMO ATRIBUTO EN LA MISMA VARIACIÓN',
            ]);
        }

        return null;
    }

    /**
     * Todas las variaciones de un producto deben usar exactamente el mismo
     * conjunto de atributos (ej: si la primera usa Color+Talla, todas deben usarlo).
     */
    private function validarConsistenciaAtributos($producto_id, array $propiedad_ids, ?string $ignore_id = null)
    {
        $nuevos_atributo_ids = Propiedad::whereIn('prp_id', $propiedad_ids)
            ->pluck('prp_atbid')->sort()->values();

        $query = VariacionProducto::where('var_prdid', $producto_id);
        if ($ignore_id) {
            $query->where('var_proid', '<>', $ignore_id);
        }
        $primera_existente = $query->with('propiedades')->first();

        if ($primera_existente) {
            $atributo_ids_existentes = $primera_existente->propiedades->pluck('prp_atbid')->sort()->values();

            if ($atributo_ids_existentes->toArray() !== $nuevos_atributo_ids->toArray()) {
                return response()->json([
                    'message' => 403,
                    'message_text' => 'DEBES USAR LOS MISMOS ATRIBUTOS QUE LAS DEMÁS VARIACIONES DE ESTE PRODUCTO',
                ]);
            }
        }

        return null;
    }

    private function buscarCombinacionDuplicada($producto_id, array $propiedad_ids, ?string $ignore_id = null)
    {
        sort($propiedad_ids);

        $query = VariacionProducto::where('var_prdid', $producto_id)
            ->whereHas('propiedades', function ($q) use ($propiedad_ids) {
                $q->whereIn('prp_id', $propiedad_ids);
            }, '=', count($propiedad_ids))
            ->with('propiedades');

        if ($ignore_id) {
            $query->where('var_proid', '<>', $ignore_id);
        }

        foreach ($query->get() as $variacion) {
            $ids = $variacion->propiedades->pluck('prp_id')->sort()->values()->toArray();
            if ($ids === $propiedad_ids) {
                return $variacion;
            }
        }

        return null;
    }

    private function mapVariacion(VariacionProducto $v): array
    {
        return [
            'var_proid' => $v->var_proid,
            'var_prdid' => $v->var_prdid,
            'var_preadi' => $v->var_preadi,
            'var_stock' => $v->var_stock,
            'var_stkmin' => $v->var_stkmin,
            'var_estado' => $v->var_estado,
            'propiedades' => $v->propiedades->map(fn($p) => [
                'prp_id' => $p->prp_id,
                'prp_nombre' => $p->prp_nombre,
                'atb_id' => $p->atributo->atb_id ?? null,
                'atb_nombre' => $p->atributo->atb_nombre ?? null,
            ]),
        ];
    }
}

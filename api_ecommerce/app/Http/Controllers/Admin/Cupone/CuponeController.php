<?php

namespace App\Http\Controllers\Admin\Cupone;

use Illuminate\Http\Request;
use App\Models\Cupone\Cupone;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Cupone\CuponeBrand;
use App\Http\Controllers\Controller;
use App\Models\Cupone\CuponeProduct;
use App\Models\Cupone\CuponeCategorie;
use App\Http\Resources\Cupone\CuponeResource;
use App\Http\Resources\Cupone\CuponeCollection;

class CuponeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // FIX: se agrega eager loading de las relaciones anidadas que usa
        // CuponeResource (products.product, categories.categorie, brands.brand).
        // Sin esto, cada fila dispara consultas "lazy" al armar el resource y,
        // si algun pivote apunta a un producto/categoria/marca ya eliminado,
        // o si tu proyecto tiene el modo estricto de Eloquent activado
        // (Model::preventLazyLoading en AppServiceProvider), el listado
        // truena en silencio para el frontend (por eso "no aparecen los
        // registros" aunque si existan en la BD).
        $cupones = Cupone::where("code","like","%".$request->search."%")
            ->with(['products.product.publicacionWeb', 'categories.categorie', 'brands.brand'])
            ->orderBy("id","desc")
            ->paginate(25);

        return response()->json([
            "total" => $cupones->total(),
            "cupones" => CuponeCollection::make($cupones),
        ]);
    }

    /**
     * Devuelve productos, categorias y marcas disponibles para
     * armar el cupon (usado en el formulario de crear/editar).
     */
    public function config(){
        // FIX: se quito el filtro where("prd_estado", 2) porque ese valor
        // no coincidia con tu convencion real de "activo" y dejaba la lista
        // de productos vacia. Si quieres filtrar solo productos activos,
        // agrega de nuevo ->where("prd_estado", TU_VALOR_DE_ACTIVO) una vez
        // que confirmes cual es ese valor en tu tabla "producto".
        $products = Producto::with('publicacionWeb')->orderBy("prd_id","desc")->get();

        // "Primer nivel" = categorias sin padre (cat_idpadr es NULL).
        // Si prefieres usar el nivel explicito, cambia por: ->where("cat_nivel", 1)
        $categories = Categoria::whereNull("cat_idpadr")->orderBy("cat_id","desc")->get();

        // La tabla "marca" no tiene columna de estado, se listan todas.
        $brands = Marca::orderBy("mar_id","desc")->get();

        return response()->json([
            "products" => $products->map(function($product) {
                return [
                    "id" => $product->prd_id,
                    // prd_titulo se movió a publicacion_web.pub_titulo; si el
                    // producto todavía no fue publicado, se muestra su código.
                    "title" => $product->publicacionWeb?->pub_titulo ?? $product->prd_codinv,
                ];
            }),
            "categories" => $categories->map(function($categorie) {
                return [
                    "id" => $categorie->cat_id,
                    "name" => $categorie->cat_nombre,
                ];
            }),
            "brands" => $brands->map(function($brand) {
                return [
                    "id" => $brand->mar_id,
                    "name" => $brand->mar_descri,
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // product_selected , categorie_selected , brand_selected
        $IS_EXIST = Cupone::where("code",$request->code)->first();
        if($IS_EXIST){
            return response()->json(["message" => 403,"message_text" => "EL CUPON YA EXISTE, DIGITE OTRO POR FAVOR"]);
        }

        $CUPONE = Cupone::create($request->all());

        foreach ($request->product_selected as $key => $product_selec) {
            CuponeProduct::create([
                "cupone_id" => $CUPONE->id,
                "product_id" => $product_selec["id"],
            ]);
        }

        foreach ($request->categorie_selected as $key => $categorie_selec) {
            CuponeCategorie::create([
                "cupone_id" => $CUPONE->id,
                "categorie_id" => $categorie_selec["id"],
            ]);
        }

        foreach ($request->brand_selected as $key => $brand_selec) {
            CuponeBrand::create([
                "cupone_id" => $CUPONE->id,
                "brand_id" => $brand_selec["id"],
            ]);
        }

        return response()->json(["message" => 200]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $CUPONE = Cupone::findOrFail($id);

        return response()->json(["cupone" => CuponeResource::make($CUPONE)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // product_selected , categorie_selected , brand_selected
        $IS_EXIST = Cupone::where("code",$request->code)->where("id","<>",$id)->first();
        if($IS_EXIST){
            return response()->json(["message" => 403,"message_text" => "EL CUPON YA EXISTE, DIGITE OTRO POR FAVOR"]);
        }

        $CUPONE = Cupone::findOrFail($id);
        $CUPONE->update($request->all());

        foreach ($CUPONE->categories as $key => $categorie) {
            $categorie->delete();
        }

        foreach ($CUPONE->products as $key => $product) {
            $product->delete();
        }

        foreach ($CUPONE->brands as $key => $brand) {
            $brand->delete();
        }

        foreach ($request->product_selected as $key => $product_selec) {
            CuponeProduct::create([
                "cupone_id" => $CUPONE->id,
                "product_id" => $product_selec["id"],
            ]);
        }
        foreach ($request->categorie_selected as $key => $categorie_selec) {
            // FIX: la clave debia ser "categorie_id" (antes decia "product_id" y
            // se perdia el dato porque no esta en $fillable de CuponeCategorie).
            CuponeCategorie::create([
                "cupone_id" => $CUPONE->id,
                "categorie_id" => $categorie_selec["id"],
            ]);
        }
        foreach ($request->brand_selected as $key => $brand_selec) {
            // FIX: la clave debia ser "brand_id" (antes decia "product_id" y
            // se perdia el dato porque no esta en $fillable de CuponeBrand).
            CuponeBrand::create([
                "cupone_id" => $CUPONE->id,
                "brand_id" => $brand_selec["id"],
            ]);
        }

        return response()->json(["message" => 200]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $CUPONE = Cupone::findOrFail($id);
        $CUPONE->delete();
        //CUANDO HAY UNA COMPRA RELACIONADA CON EL CUPON YA NO SE PUEDE ELIMINAR
        return response()->json(["message" => 200]);
    }
}
<?php

namespace App\Http\Controllers\Admin\Discount;

use Illuminate\Http\Request;
use App\Models\Discount\Discount;
use App\Http\Controllers\Controller;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountProduct;
use App\Models\Discount\DiscountCategorie;
use App\Http\Resources\Discount\DiscountResource;
use App\Http\Resources\Discount\DiscountCollection;

class DiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // FIX (mismo que en CuponeController): se agrega eager loading de
        // las relaciones anidadas que usa DiscountResource, para evitar que
        // el listado truene en silencio si algun pivote apunta a un
        // producto/categoria/marca ya eliminado.
        $discounts = Discount::where("code","like","%".$request->search."%")
            ->with(['products.product', 'categories.categorie', 'brands.brand'])
            ->orderBy("id","desc")
            ->paginate(25);

        return response()->json([
            "total" => $discounts->total(),
            "discounts" => DiscountCollection::make($discounts),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Valida que el/los producto(s), categoria(s) o marca(s) elegidos no
        // esten ya dentro de otra campaña del MISMO tipo (discount_type) y
        // MISMO canal (type_campaing) en un rango de fechas que se solape.
        if($request->discount_type == 1){
            foreach ($request->product_selected as $key => $product_selected) {
                $EXIST_DISCOUNT_START_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("products",function($q) use($product_selected) {
                                                            $q->where("product_id",$product_selected["id"]);
                                                        })->whereBetween("start_date",[$request->start_date,$request->end_date])
                                                        ->first();
                $EXIST_DISCOUNT_END_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("products",function($q) use($product_selected) {
                                                            $q->where("product_id",$product_selected["id"]);
                                                        })->whereBetween("end_date",[$request->start_date,$request->end_date])
                                                        ->first();

                if($EXIST_DISCOUNT_START_DATE || $EXIST_DISCOUNT_END_DATE){
                    return response()->json(["message" => 403,"message_text" => "EL PRODUCTO ".$product_selected["title"].
                    " YA SE ENCUENTRA EN UNA CAMPAÑA DE DESCUENTO QUE SERIA ".($EXIST_DISCOUNT_START_DATE ? '#'.$EXIST_DISCOUNT_START_DATE->code : '')
                    .($EXIST_DISCOUNT_END_DATE ? '#'.$EXIST_DISCOUNT_END_DATE->code : '')]);
                }
            }
        }

        if($request->discount_type == 2){
            foreach ($request->categorie_selected as $key => $categorie_selected) {
                $EXIST_DISCOUNT_START_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("categories",function($q) use($categorie_selected) {
                                                            $q->where("categorie_id",$categorie_selected["id"]);
                                                        })->whereBetween("start_date",[$request->start_date,$request->end_date])
                                                        ->first();
                $EXIST_DISCOUNT_END_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("categories",function($q) use($categorie_selected) {
                                                            $q->where("categorie_id",$categorie_selected["id"]);
                                                        })->whereBetween("end_date",[$request->start_date,$request->end_date])
                                                        ->first();

                if($EXIST_DISCOUNT_START_DATE || $EXIST_DISCOUNT_END_DATE){
                    return response()->json(["message" => 403,"message_text" => "LA CATEGORIA ".$categorie_selected["name"].
                    " YA SE ENCUENTRA EN UNA CAMPAÑA DE DESCUENTO QUE SERIA ".($EXIST_DISCOUNT_START_DATE ? '#'.$EXIST_DISCOUNT_START_DATE->code : '')
                    .($EXIST_DISCOUNT_END_DATE ? '#'.$EXIST_DISCOUNT_END_DATE->code : '')]);
                }
            }
        }

        if($request->discount_type == 3){
            foreach ($request->brand_selected as $key => $brand_selected) {
                $EXIST_DISCOUNT_START_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("brands",function($q) use($brand_selected) {
                                                            $q->where("brand_id",$brand_selected["id"]);
                                                        })->whereBetween("start_date",[$request->start_date,$request->end_date])
                                                        ->first();
                $EXIST_DISCOUNT_END_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("brands",function($q) use($brand_selected) {
                                                            $q->where("brand_id",$brand_selected["id"]);
                                                        })->whereBetween("end_date",[$request->start_date,$request->end_date])
                                                        ->first();

                if($EXIST_DISCOUNT_START_DATE || $EXIST_DISCOUNT_END_DATE){
                    return response()->json(["message" => 403,"message_text" => "LA MARCA ".$brand_selected["name"].
                    " YA SE ENCUENTRA EN UNA CAMPAÑA DE DESCUENTO QUE SERIA ".($EXIST_DISCOUNT_START_DATE ? '#'.$EXIST_DISCOUNT_START_DATE->code : '')
                    .($EXIST_DISCOUNT_END_DATE ? '#'.$EXIST_DISCOUNT_END_DATE->code : '')]);
                }
            }
        }

        // El codigo de la campaña se genera solo (no lo digita el admin,
        // a diferencia del cupon). Se usa como slug para el link publico
        // cuando type_campaing = 3 (Campaña Link).
        $request->request->add(["code" => uniqid()]);
        $discount = Discount::create($request->all());

        foreach ($request->product_selected as $key => $product_selec) {
            DiscountProduct::create([
                "discount_id" => $discount->id,
                "product_id" => $product_selec["id"],
            ]);
        }

        foreach ($request->categorie_selected as $key => $categorie_selec) {
            DiscountCategorie::create([
                "discount_id" => $discount->id,
                "categorie_id" => $categorie_selec["id"],
            ]);
        }

        foreach ($request->brand_selected as $key => $brand_selec) {
            DiscountBrand::create([
                "discount_id" => $discount->id,
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
        $discount = Discount::findOrFail($id);

        return response()->json([
            "discount" => DiscountResource::make($discount),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if($request->discount_type == 1){
            foreach ($request->product_selected as $key => $product_selected) {
                $EXIST_DISCOUNT_START_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("id","<>",$id)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("products",function($q) use($product_selected) {
                                                            $q->where("product_id",$product_selected["id"]);
                                                        })->whereBetween("start_date",[$request->start_date,$request->end_date])
                                                        ->first();
                $EXIST_DISCOUNT_END_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("id","<>",$id)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("products",function($q) use($product_selected) {
                                                            $q->where("product_id",$product_selected["id"]);
                                                        })->whereBetween("end_date",[$request->start_date,$request->end_date])
                                                        ->first();

                if($EXIST_DISCOUNT_START_DATE || $EXIST_DISCOUNT_END_DATE){
                    return response()->json(["message" => 403,"message_text" => "EL PRODUCTO ".$product_selected["title"].
                    " YA SE ENCUENTRA EN UNA CAMPAÑA DE DESCUENTO QUE SERIA ".($EXIST_DISCOUNT_START_DATE ? '#'.$EXIST_DISCOUNT_START_DATE->code : '')
                    .($EXIST_DISCOUNT_END_DATE ? '#'.$EXIST_DISCOUNT_END_DATE->code : '')]);
                }
            }
        }

        if($request->discount_type == 2){
            foreach ($request->categorie_selected as $key => $categorie_selected) {
                $EXIST_DISCOUNT_START_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("id","<>",$id)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("categories",function($q) use($categorie_selected) {
                                                            $q->where("categorie_id",$categorie_selected["id"]);
                                                        })->whereBetween("start_date",[$request->start_date,$request->end_date])
                                                        ->first();
                $EXIST_DISCOUNT_END_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("id","<>",$id)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("categories",function($q) use($categorie_selected) {
                                                            $q->where("categorie_id",$categorie_selected["id"]);
                                                        })->whereBetween("end_date",[$request->start_date,$request->end_date])
                                                        ->first();

                if($EXIST_DISCOUNT_START_DATE || $EXIST_DISCOUNT_END_DATE){
                    return response()->json(["message" => 403,"message_text" => "LA CATEGORIA ".$categorie_selected["name"].
                    " YA SE ENCUENTRA EN UNA CAMPAÑA DE DESCUENTO QUE SERIA ".($EXIST_DISCOUNT_START_DATE ? '#'.$EXIST_DISCOUNT_START_DATE->code : '')
                    .($EXIST_DISCOUNT_END_DATE ? '#'.$EXIST_DISCOUNT_END_DATE->code : '')]);
                }
            }
        }

        if($request->discount_type == 3){
            foreach ($request->brand_selected as $key => $brand_selected) {
                $EXIST_DISCOUNT_START_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("id","<>",$id)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("brands",function($q) use($brand_selected) {
                                                            $q->where("brand_id",$brand_selected["id"]);
                                                        })->whereBetween("start_date",[$request->start_date,$request->end_date])
                                                        ->first();
                $EXIST_DISCOUNT_END_DATE = Discount::where("type_campaing",$request->type_campaing)
                                                        ->where("id","<>",$id)
                                                        ->where("discount_type",$request->discount_type)
                                                        ->whereHas("brands",function($q) use($brand_selected) {
                                                            $q->where("brand_id",$brand_selected["id"]);
                                                        })->whereBetween("end_date",[$request->start_date,$request->end_date])
                                                        ->first();

                if($EXIST_DISCOUNT_START_DATE || $EXIST_DISCOUNT_END_DATE){
                    return response()->json(["message" => 403,"message_text" => "LA MARCA ".$brand_selected["name"].
                    " YA SE ENCUENTRA EN UNA CAMPAÑA DE DESCUENTO QUE SERIA ".($EXIST_DISCOUNT_START_DATE ? '#'.$EXIST_DISCOUNT_START_DATE->code : '')
                    .($EXIST_DISCOUNT_END_DATE ? '#'.$EXIST_DISCOUNT_END_DATE->code : '')]);
                }
            }
        }

        $discount = Discount::findOrFail($id);
        $discount->update($request->all());

        foreach ($discount->categories as $key => $categorie) {
            $categorie->delete();
        }

        foreach ($discount->products as $key => $product) {
            $product->delete();
        }

        foreach ($discount->brands as $key => $brand) {
            $brand->delete();
        }

        foreach ($request->product_selected as $key => $product_selec) {
            DiscountProduct::create([
                "discount_id" => $discount->id,
                "product_id" => $product_selec["id"],
            ]);
        }

        foreach ($request->categorie_selected as $key => $categorie_selec) {
            DiscountCategorie::create([
                "discount_id" => $discount->id,
                "categorie_id" => $categorie_selec["id"],
            ]);
        }

        foreach ($request->brand_selected as $key => $brand_selec) {
            DiscountBrand::create([
                "discount_id" => $discount->id,
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
        $discount = Discount::findOrFail($id);
        $discount->delete();
        // Si la campaña ya esta ligada a una venta, evalua si deberias
        // bloquear el borrado aqui (igual que en CuponeController::destroy).
        return response()->json(["message" => 200]);
    }
}

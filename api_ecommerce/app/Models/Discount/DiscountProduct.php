<?php

namespace App\Models\Discount;

use Carbon\Carbon;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// AJUSTA el "use App\Models\Producto;" de arriba si tu modelo real
// esta en otra carpeta/namespace (el mismo que usaste para arreglar
// CuponeProduct.php).

class DiscountProduct extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        "discount_id",
        "product_id",
    ];

    public function setCreatedAtAttribute($value){
        date_default_timezone_set("America/Lima");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedtAttribute($value){
        date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"] = Carbon::now();
    }
    public function product(){
        return $this->belongsTo(Producto::class, 'product_id', 'prd_id');
    }
}

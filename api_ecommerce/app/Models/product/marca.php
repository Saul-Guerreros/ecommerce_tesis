<?php

namespace App\Models\product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class marca extends Model
{
    protected $table = 'marca';
    protected $primaryKey = 'mar_id';
    public $timestamps = false;

    protected $fillable = [
        'mar_descri',
    ];
}

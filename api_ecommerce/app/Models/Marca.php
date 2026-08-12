<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    public $timestamps = false;

    protected $table = 'marca';

    protected $primaryKey = 'mar_id';

    protected $fillable = [
        'mar_descri',
    ];
}

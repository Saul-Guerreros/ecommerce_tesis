<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barrio extends Model
{
    protected $table = 'barrio';
    protected $primaryKey = 'bar_id';
    public $timestamps = false;

    protected $fillable = [
        'bar_ciuid',
        'bar_descri',
        'bar_estado',
    ];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'bar_ciuid', 'ciu_id');
    }
}

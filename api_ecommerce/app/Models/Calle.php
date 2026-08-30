<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calle extends Model
{
    protected $table = 'calle';
    protected $primaryKey = 'cal_id';
    public $timestamps = false;

    protected $fillable = [
        'cal_ciuid',
        'cal_descri',
        'cal_estado',
    ];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'cal_ciuid', 'ciu_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sesion extends Model
{
    protected $table = 'sesion';
    protected $primaryKey = 'ses_id';
    public $timestamps = false;

    protected $fillable = [
        'ses_usuid',
        'ses_tokhas',
        'ses_iporig',
        'ses_userag',
        'ses_fecexp',
        'ses_revoca',
    ];

    protected $casts = [
        'ses_fecexp' => 'datetime',
        'ses_creado' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ses_usuid', 'usu_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('ses_revoca', 0)->where('ses_fecexp', '>', now());
    }
}

<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class CategoriaFalla extends Model
{
    public const OTRO = 'OTRO';

    protected $table = 'categoria_falla';
    protected $primaryKey = 'caf_id';
    public $timestamps = false;

    protected $fillable = ['caf_codigo', 'caf_descri', 'caf_estado'];

    protected $casts = [
        'caf_estado' => 'integer',
        'caf_creado' => 'datetime',
    ];

    public function fallas()
    {
        return $this->hasMany(Falla::class, 'fll_cafid', 'caf_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('caf_estado', 1);
    }

    public static function otroId(): ?int
    {
        static $id = null;

        return $id ??= static::where('caf_codigo', self::OTRO)->value('caf_id');
    }
}
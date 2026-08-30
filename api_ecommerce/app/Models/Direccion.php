<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Direccion extends Model
{
    protected $table = 'direccion';
    protected $primaryKey = 'dir_id';

    // Mapeamos los timestamps propios del DER a los de Eloquent
    // para que created_at/updated_at se gestionen automaticamente.
    const CREATED_AT = 'dir_creado';
    const UPDATED_AT = 'dir_modific';

    protected $fillable = [
        'dir_perid',
        'dir_barid',
        'dir_calid',
        'dir_nrocasa',
        'dir_referencia',
        'dir_celular',
        'dir_telef',
        'dir_predet',
        'dir_estado',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'dir_perid', 'per_id');
    }

    public function barrio()
    {
        return $this->belongsTo(Barrio::class, 'dir_barid', 'bar_id');
    }

    public function calle()
    {
        return $this->belongsTo(Calle::class, 'dir_calid', 'cal_id');
    }
}

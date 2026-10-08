<?php

namespace App\Models\Carrito;

use App\Models\Usuario;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Carrito extends Model{

    use HasFactory, SoftDeletes;

    protected $table= 'carrito';
    protected $primaryKey= 'car_id';

    const CREATED_AT= 'car_creado';
    const UPDATED_AT= 'car_actual';
    const DELETED_AT= 'car_elimin';

    protected $fillable= [
        'car_idusu',
        'car_idpro',
        'car_tipdes',
        'car_descue',
        'car_tipcam',
        'car_codcup',
        'car_coddes',
        'car_cantid',
        'car_precio',
        'car_subtot',
        'car_total',
        'car_modpag',
        'car_numcuo',
        'car_recargopct',
        'car_montocuo',
    ];

    protected $casts= [
        'car_idusu'=> 'integer',
        'car_idpro'=> 'integer',
        'car_tipdes'=> 'integer',
        'car_tipcam'=> 'integer',
        'car_descue'=> 'decimal:2',
        'car_cantid'=> 'decimal:2',
        'car_precio'=> 'decimal:2',
        'car_subtot'=> 'decimal:2',
        'car_total'=> 'decimal:2',
        'car_creado'=> 'datetime',
        'car_actual'=> 'datetime',
        'car_elimin'=> 'datetime',
        'car_numcuo'     => 'integer',
        'car_recargopct' => 'decimal:2',
        'car_montocuo'   => 'decimal:2',
    ];

    public function usuario(): BelongsTo{
        return $this->belongsTo(Usuario::class, 'car_idusu', 'usu_id');
    }

    public function producto(): BelongsTo{
        return $this->belongsTo(Producto::class, 'car_idpro', 'prd_id');
    }

}

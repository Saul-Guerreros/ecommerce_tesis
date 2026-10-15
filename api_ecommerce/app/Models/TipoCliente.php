<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCliente extends Model
{
    protected $table = 'tipo_cliente';
    protected $primaryKey = 'tpc_id';
    public $timestamps = false;

    protected $fillable = [
        'tpc_descri',
        'tpc_estado',
    ];

    protected $casts = [
        'tpc_id'     => 'integer',
        'tpc_estado' => 'integer',
    ];

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'cli_tpcid', 'tpc_id');
    }
}

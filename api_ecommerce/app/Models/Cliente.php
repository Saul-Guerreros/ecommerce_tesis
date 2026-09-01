<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'cli_id';
    public $timestamps = false;

    protected $fillable = [
        'cli_perid',
        'cli_tpcid',
        'cli_ruc',
        'cli_estado',
        'cli_creado',
    ];

    protected $casts = [
        'cli_estado' => 'integer',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'cli_perid', 'per_id');
    }

    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class, 'cli_tpcid', 'tpc_id');
    }

    // Descomentar cuando exista el modelo Pedido
    // public function pedidos()
    // {
    //     return $this->hasMany(Pedido::class, 'ped_cliid', 'cli_id');
    // }
}
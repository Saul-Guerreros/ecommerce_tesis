<?php
// app/Models/Compras/TipoAjuste.php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class TipoAjuste extends Model
{
    protected $table      = 'tipo_ajuste';
    protected $primaryKey = 'tpa_id';
    public    $timestamps = false;

    protected $fillable = ['tpa_codig', 'tpa_descri', 'tpa_natura', 'tpa_reqcom', 'tpa_estado'];

    protected $casts = [
        'tpa_id'     => 'integer',
        'tpa_reqcom' => 'integer',
        'tpa_estado' => 'integer',
    ];
}
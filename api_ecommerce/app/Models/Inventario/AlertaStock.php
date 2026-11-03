<?php
// app/Models/Inventario/AlertaStock.php
namespace App\Models\Inventario;

use App\Models\Configuracion\Deposito;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AlertaStock extends Model
{
    // --- Tipos (de mayor a menor urgencia) ---
    public const TIPO_AGOTADO    = 'agotado';
    public const TIPO_CRITICO    = 'critico';
    public const TIPO_REPONER    = 'reponer';
    public const TIPO_SOBRESTOCK = 'sobrestock';

    public const TIPOS = [
        self::TIPO_AGOTADO, self::TIPO_CRITICO, self::TIPO_REPONER, self::TIPO_SOBRESTOCK,
    ];

    // --- Estados ---
    public const ESTADO_PENDIENTE  = 1;
    public const ESTADO_ATENDIDA   = 2;
    public const ESTADO_DESCARTADA = 3;

    protected $table      = 'alerta_stock';
    protected $primaryKey = 'ast_id';
    public    $timestamps = false;

    protected $fillable = [
        'ast_prdid', 'ast_depid', 'ast_tipo', 'ast_dispon', 'ast_demdia', 'ast_ss',
        'ast_rop', 'ast_sugeri', 'ast_cobert', 'ast_ltdias', 'ast_modelo', 'ast_estado',
        'ast_usuid', 'ast_obs', 'ast_ntipo', 'ast_creado', 'ast_actual', 'ast_cerrado',
    ];

    protected $casts = [
        'ast_id'      => 'integer',
        'ast_prdid'   => 'integer',
        'ast_depid'   => 'integer',
        'ast_dispon'  => 'decimal:4',
        'ast_demdia'  => 'decimal:4',
        'ast_ss'      => 'decimal:4',
        'ast_rop'     => 'decimal:4',
        'ast_sugeri'  => 'decimal:4',
        'ast_cobert'  => 'decimal:2',
        'ast_ltdias'  => 'integer',
        'ast_estado'  => 'integer',
        'ast_usuid'   => 'integer',
        'ast_creado'  => 'datetime',
        'ast_actual'  => 'datetime',
        'ast_cerrado' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'ast_prdid', 'prd_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'ast_depid', 'dpo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ast_usuid', 'usu_id');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('ast_estado', self::ESTADO_PENDIENTE);
    }

    public function estaPendiente(): bool
    {
        return $this->ast_estado === self::ESTADO_PENDIENTE;
    }
}

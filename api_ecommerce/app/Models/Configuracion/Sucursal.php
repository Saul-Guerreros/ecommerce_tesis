<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table      = 'sucursal';
    protected $primaryKey = 'suc_id';
    public    $timestamps = false;

    protected $fillable = [
        'suc_empid', 'suc_ciuid', 'suc_nombre', 'suc_princi', 'suc_establ',
        'suc_direccion', 'suc_telef', 'suc_cel', 'suc_email', 'suc_estado',
        'suc_retiro', 'suc_horario', 'suc_diaslab', 'suc_webstk', 'suc_delivery',
    ];

    /** Lunes a sábado: el horario real de la casa matriz. */
    public const DIAS_LABORABLES_DEFECTO = '1111110';

    protected $casts = [
        'suc_id' => 'integer', 'suc_empid' => 'integer', 'suc_ciuid' => 'integer',
        'suc_princi' => 'integer', 'suc_estado' => 'integer', 'suc_creado' => 'datetime',
        'suc_retiro' => 'boolean', 'suc_webstk' => 'boolean', 'suc_delivery' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'suc_empid', 'emp_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'suc_ciuid', 'ciu_id');
    }

    public function setSucEmailAttribute($value): void
    {
        $this->attributes['suc_email'] = $value ? strtolower(trim($value)) : null;
    }

    /**
     * ¿La sucursal atiende ese día?
     *
     * La máscara suc_diaslab tiene 7 caracteres '1'/'0' de lunes a
     * domingo, alineada con dayOfWeekIso (1 = lunes ... 7 = domingo).
     *
     * Si la máscara está vacía o mal formada, se cae al valor por defecto
     * en vez de devolver false: una sucursal sin ningún día laborable
     * haría que el cálculo del plazo de retiro no termine nunca.
     */
    public function esDiaLaborable(\Carbon\CarbonInterface $fecha): bool
    {
        $mascara = $this->suc_diaslab;

        if (!is_string($mascara) || strlen($mascara) !== 7 || !str_contains($mascara, '1')) {
            $mascara = self::DIAS_LABORABLES_DEFECTO;
        }

        return $mascara[$fecha->dayOfWeekIso - 1] === '1';
    }

    public function scopeParaRetiro($query)
    {
        return $query->where('suc_estado', 1)->where('suc_retiro', true);
    }

    /**
     * Sucursales que el administrador decidió exponer en el modal
     * "Ver Stock en Tiendas" del detalle de producto.
     *
     * Se separa de scopeParaRetiro() a propósito: mostrar existencias y
     * atender retiros son decisiones distintas. Una sucursal puede querer
     * publicar su stock como referencia aunque no entregue pedidos web.
     */
    public function scopeVisibleEnStockWeb($query)
    {
        return $query->where('suc_estado', 1)->where('suc_webstk', true);
    }
}
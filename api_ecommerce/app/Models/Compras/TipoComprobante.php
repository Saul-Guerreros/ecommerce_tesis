<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    protected $table      = 'tipo_comprobante';
    protected $primaryKey = 'tco_id';
    public    $timestamps = false;
    /** Codigo del seeder para Factura. */
    public const CODIGO_FACTURA = '001';

    /** Codigo del seeder para Nota de Credito. */
    public const CODIGO_NOTA_CREDITO = '002';

    public static function idNotaCredito(): ?int
    {
        return static::where('tco_codigo', self::CODIGO_NOTA_CREDITO)
            ->where('tco_estado', 1)
            ->value('tco_id');
    }

    protected $fillable = [
        'tco_codigo',
        'tco_descri',
        'tco_reqtim',
        'tco_afecstk',
        'tco_estado',
    ];

    protected $casts = [
        'tco_id'      => 'integer',
        'tco_reqtim'  => 'integer',
        'tco_afecstk' => 'integer',
        'tco_estado'  => 'integer',
        'tco_creado'  => 'datetime',
    ];

    public static function idFactura(): ?int
    {
        return static::where('tco_codigo', self::CODIGO_FACTURA)
            ->where('tco_estado', 1)
            ->value('tco_id');
    }

    public function timbrados()
    {
        return $this->hasMany(Timbrado::class, 'tim_tcoid', 'tco_id');
    }

    // El codigo es numerico de 3 digitos: se completa con ceros a la
    // izquierda para que "1" y "001" no convivan como filas distintas.
    public function setTcoCodigoAttribute($value): void
    {
        $this->attributes['tco_codigo'] = str_pad(trim($value), 3, '0', STR_PAD_LEFT);
    }

    public function setTcoDescriAttribute($value): void
    {
        $this->attributes['tco_descri'] = trim($value);
    }
}

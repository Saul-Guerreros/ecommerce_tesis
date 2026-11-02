<?php
// tests/Unit/Inventario/CalculoReposicionTest.php
namespace Tests\Unit\Inventario;

use App\Models\Inventario\AlertaStock;
use App\Services\Inventario\CalculoReposicion;
use PHPUnit\Framework\TestCase;

class CalculoReposicionTest extends TestCase
{
    private const Z95 = 1.6452;

    public function test_factor_z_de_niveles_de_servicio_conocidos(): void
    {
        $this->assertEqualsWithDelta(1.2816, CalculoReposicion::factorZ(90), 0.001);
        $this->assertEqualsWithDelta(1.6449, CalculoReposicion::factorZ(95), 0.001);
        $this->assertEqualsWithDelta(2.3263, CalculoReposicion::factorZ(99), 0.001);
    }

    public function test_formulas_de_stock_de_seguridad_y_punto_de_reorden(): void
    {
        // d = 14 u/sem, σ = 4, LT = 7 días (1 semana), R = 4 semanas
        $r = CalculoReposicion::calcular(10, 14, 4, 7, self::Z95, 4, 12);

        $this->assertEqualsWithDelta(6.5808, $r['ss'], 0.001);          // 1.6452 · 4 · √1
        $this->assertEqualsWithDelta(20.5808, $r['rop'], 0.001);        // 14 · 1 + SS
        $this->assertEqualsWithDelta(76.5808, $r['objetivo'], 0.001);   // 14 · (1 + 4) + SS
        $this->assertSame(67.0, $r['sugerido']);                        // ⌈76.58 − 10⌉
        $this->assertSame(AlertaStock::TIPO_REPONER, $r['tipo']);
    }

    public function test_clasificacion_de_alertas(): void
    {
        $tipo = fn (float $disp) => CalculoReposicion::calcular($disp, 14, 4, 7, self::Z95, 4, 12)['tipo'];

        $this->assertSame(AlertaStock::TIPO_AGOTADO, $tipo(0));
        $this->assertSame(AlertaStock::TIPO_CRITICO, $tipo(5));      // ≤ SS (6.58)
        $this->assertSame(AlertaStock::TIPO_REPONER, $tipo(15));     // ≤ ROP (20.58)
        $this->assertNull($tipo(40));                                // nivel normal
        $this->assertSame(AlertaStock::TIPO_SOBRESTOCK, $tipo(200)); // > 12 semanas de cobertura
    }

    public function test_sin_demanda_no_genera_alerta(): void
    {
        $r = CalculoReposicion::calcular(30, 0, 0, 7, self::Z95, 4, 12);

        $this->assertNull($r['tipo']);
        $this->assertNull($r['cobertura_dias']);
        $this->assertSame(0.0, $r['sugerido']);
    }

    public function test_mayor_lead_time_aumenta_stock_de_seguridad(): void
    {
        $corto = CalculoReposicion::calcular(10, 14, 4, 7, self::Z95, 4, 12);
        $largo = CalculoReposicion::calcular(10, 14, 4, 28, self::Z95, 4, 12);

        $this->assertEqualsWithDelta($corto['ss'] * 2, $largo['ss'], 0.001); // √4 = 2
    }
}
<?php
// tests/Unit/Inventario/ModelosPronosticoTest.php
namespace Tests\Unit\Inventario;

use App\Services\Inventario\Metricas;
use App\Services\Inventario\Modelos\CrostonModelo;
use App\Services\Inventario\Modelos\HoltModelo;
use App\Services\Inventario\Modelos\PromedioMovilModelo;
use App\Services\Inventario\Modelos\SbaModelo;
use App\Services\Inventario\Modelos\SesModelo;
use PHPUnit\Framework\TestCase;

class ModelosPronosticoTest extends TestCase
{
    public function test_demanda_constante_se_pronostica_igual_en_todos_los_modelos_no_sesgados(): void
    {
        $serie = array_fill(0, 20, 10.0);

        foreach ([new PromedioMovilModelo(4), new SesModelo(), new HoltModelo(), new CrostonModelo()] as $modelo) {
            $this->assertEqualsWithDelta(10.0, $modelo->pronosticar($serie, 1)[0], 0.01, $modelo->nombre());
        }
    }

    public function test_holt_proyecta_la_tendencia(): void
    {
        $serie = array_map(fn ($i) => 5.0 + $i, range(0, 19)); // 5, 6, ..., 24

        $pronostico = (new HoltModelo())->pronosticar($serie, 2);

        $this->assertEqualsWithDelta(25.0, $pronostico[0], 0.5);
        $this->assertGreaterThan($pronostico[0], $pronostico[1]);
    }

    public function test_croston_estima_tamano_sobre_intervalo(): void
    {
        // Vende 3 unidades cada 4 semanas => 0.75 por semana.
        $serie = array_merge(...array_fill(0, 5, [3.0, 0.0, 0.0, 0.0]));

        $this->assertEqualsWithDelta(0.75, (new CrostonModelo())->pronosticar($serie, 1)[0], 0.01);
    }

    public function test_sba_corrige_a_la_baja_el_sesgo_de_croston(): void
    {
        $serie = [2.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 3.0, 0.0, 1.0, 0.0, 0.0];

        $croston = (new CrostonModelo())->pronosticar($serie, 1)[0];
        $sba     = (new SbaModelo())->pronosticar($serie, 1)[0];

        $this->assertLessThan($croston, $sba);
    }

    public function test_ningun_modelo_pronostica_valores_negativos(): void
    {
        $serie = array_map(fn ($i) => max(0.0, 20.0 - 2 * $i), range(0, 15)); // caída fuerte

        foreach ([new SesModelo(), new HoltModelo(), new CrostonModelo(), new SbaModelo()] as $modelo) {
            foreach ($modelo->pronosticar($serie, 8) as $valor) {
                $this->assertGreaterThanOrEqual(0.0, $valor, $modelo->nombre());
            }
        }
    }

    public function test_metricas_de_error(): void
    {
        $real     = [2.0, 0.0, 4.0];
        $previsto = [1.0, 1.0, 1.0];

        $this->assertEqualsWithDelta(5 / 3, Metricas::mae($real, $previsto), 0.0001);
        $this->assertEqualsWithDelta(sqrt(11 / 3), Metricas::rmse($real, $previsto), 0.0001);
        $this->assertEqualsWithDelta(5 / 6, Metricas::wape($real, $previsto), 0.0001);
        $this->assertNull(Metricas::wape([0.0, 0.0], [1.0, 1.0]));
    }
}
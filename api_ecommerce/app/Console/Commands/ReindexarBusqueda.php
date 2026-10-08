<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\PublicacionWeb;
use App\Services\Catalogo\FichaTecnicaService;
use App\Services\Catalogo\IndiceBusquedaService;
use Illuminate\Console\Command;

/**
 * Reconstruye el índice de búsqueda y, con --diagnostico, lista las
 * publicaciones publicadas cuyos datos dificultan encontrarlas.
 *
 *   php artisan busqueda:reindexar
 *   php artisan busqueda:reindexar --diagnostico
 *   php artisan busqueda:reindexar --solo-diagnostico
 */
class ReindexarBusqueda extends Command
{
    protected $signature = 'busqueda:reindexar
                            {--diagnostico : Además de reindexar, revisar la calidad de los datos}
                            {--solo-diagnostico : Revisar la calidad de los datos sin reindexar}';

    protected $description = 'Reconstruye el índice de búsqueda de la tienda y revisa la calidad del catálogo';

    /** Descripción mínima recomendada (caracteres) para que aporte al ranking. */
    private const DESCRIPCION_MINIMA = 80;

    public function handle(IndiceBusquedaService $indice, FichaTecnicaService $ficha): int
    {
        if (! $this->option('solo-diagnostico')) {
            $total = PublicacionWeb::publicadas()->count();
            $this->info("Reindexando {$total} publicación(es) publicada(s)...");

            $barra = $this->output->createProgressBar($total);
            $filas = $indice->reindexarTodo(fn () => $barra->advance());
            $barra->finish();

            $this->newLine(2);
            $this->info("Índice listo: {$filas} fila(s).");
        }

        if ($this->option('diagnostico') || $this->option('solo-diagnostico')) {
            $this->diagnosticar($ficha);
        }

        return self::SUCCESS;
    }

    private function diagnosticar(FichaTecnicaService $ficha): void
    {
        $problemas = [];
        $raices = Categoria::whereNull('cat_idpadr')->pluck('cat_id')->all();

        PublicacionWeb::publicadas()
            ->with(['producto.padre', 'categoria'])
            ->orderBy('pub_id')
            ->each(function (PublicacionWeb $pub) use ($ficha, $raices, &$problemas) {
                $avisos = [];

                if (! $pub->pub_catid || in_array($pub->pub_catid, $raices)) {
                    $avisos[] = 'No está en una subcategoría';
                } else {
                    $plantilla = $ficha->plantilla($pub->pub_catid);

                    if ($plantilla->isEmpty()) {
                        $avisos[] = "La subcategoría {$pub->categoria?->cat_nombre} no tiene plantilla de ficha";
                    } else {
                        $valores = $ficha->valoresEfectivos($pub->producto, $pub);
                        $faltan = $plantilla
                            ->filter(fn ($i) => $i->cta_obliga === 1 && ! $valores->has($i->cta_atbid))
                            ->map(fn ($i) => $i->atributo->atb_nombre);

                        if ($faltan->isNotEmpty()) {
                            $avisos[] = 'Ficha incompleta: falta ' . $faltan->implode(', ');
                        }
                    }
                }

                $descripcion = trim(strip_tags((string) $pub->pub_descwb));
                if (mb_strlen($descripcion) < self::DESCRIPCION_MINIMA) {
                    $avisos[] = 'Descripción vacía o muy corta';
                }

                if (count(preg_split('/\s+/u', trim($pub->pub_titulo))) < 3) {
                    $avisos[] = 'Título poco descriptivo (ej. tipo + marca + modelo)';
                }

                foreach ($avisos as $aviso) {
                    $problemas[] = [$pub->producto?->prd_codinv, mb_strimwidth($pub->pub_titulo, 0, 45, '…'), $aviso];
                }
            });

        $this->newLine();

        if (empty($problemas)) {
            $this->info('Diagnóstico: todas las publicaciones tienen datos completos para la búsqueda.');
            return;
        }

        $this->warn('Diagnóstico: publicaciones con datos que dificultan encontrarlas');
        $this->table(['SKU', 'Publicación', 'Problema'], $problemas);
    }
}

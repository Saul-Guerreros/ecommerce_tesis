<?php
// app/Services/Inventario/Modelos/ModeloPronostico.php
namespace App\Services\Inventario\Modelos;

/**
 * Contrato común de los modelos de pronóstico (patrón Strategy).
 * Cada modelo recibe la serie semanal y devuelve las unidades previstas
 * para las próximas $horizonte semanas (nunca negativas).
 */
interface ModeloPronostico
{
    /** Identificador corto que se guarda en la base (pdd_modelo / mmo_modelo). */
    public function nombre(): string;

    /**
     * @param  float[] $serie     Demanda semanal en orden cronológico.
     * @return float[]            $horizonte valores, uno por semana futura.
     */
    public function pronosticar(array $serie, int $horizonte): array;

    /** Parámetros usados en el último pronóstico (para auditoría). */
    public function parametros(): array;
}
<?php

namespace App\Services\Facturacion\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Utilidades comunes a la declaracion de cualquier Documento Electronico:
 * escritura segura de archivos, lectura de base64 y normalizacion de la
 * respuesta de la API.
 *
 * Se extrajo a un trait cuando aparecio el segundo tipo de documento (la
 * NC). FacturacionService todavia tiene su copia privada: se puede migrar
 * despues, pero no se toco para no modificar codigo ya probado en
 * produccion.
 */
trait ManejaArchivosDe
{
    /**
     * Escribe completo a un temporal y renombra, para que nunca quede un
     * archivo a medias (guia 7.3). Disco 'local' = storage/app, privado.
     * Devuelve la ruta, o null si no se pudo escribir y verificar.
     */
    protected function guardar(string $ruta, string $contenido): ?string
    {
        $disco = Storage::disk('local');
        $tmp   = $ruta . '.tmp';

        if (!$disco->put($tmp, $contenido)) {
            return null;
        }

        if ($disco->exists($ruta)) {
            $disco->delete($ruta);
        }

        if (!$disco->move($tmp, $ruta)) {
            $disco->delete($tmp);

            return null;
        }

        return $disco->size($ruta) === strlen($contenido) ? $ruta : null;
    }

    protected function decodificar(mixed $base64): ?string
    {
        if (!is_string($base64) || $base64 === '') {
            return null;
        }

        $binario = base64_decode($base64, true);

        return ($binario === false || $binario === '') ? null : $binario;
    }

    protected function cdcValido(mixed $cdc): ?string
    {
        return is_string($cdc) && preg_match('/^[0-9]{44}$/', $cdc) ? $cdc : null;
    }

    /** Quita los null: un dato que la API no devolvio no debe pisar el que ya habia. */
    protected function sinNulos(array $datos): array
    {
        return array_filter($datos, fn ($v) => $v !== null);
    }

    /** "prueba" y "aviso" de la API juntos, para mostrarlos al usuario. */
    protected function aviso(array $c): ?string
    {
        $texto = trim(($c['prueba'] ?? '') . ' ' . ($c['aviso'] ?? ''));

        return $texto !== '' ? $texto : null;
    }
}
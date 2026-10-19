<?php

namespace App\Services\Servicio;

use App\Models\Servicio\SolicitudAdjunto;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Http\UploadedFile;

/**
 * Guarda fotos de una solicitud en storage/app/public/servicios/{sse_id}.
 * Nombre aleatorio (hashName) para que la URL no sea adivinable.
 * Devuelve las rutas guardadas para poder borrarlas si la transacción falla.
 */
class AdjuntoServicio
{
    /**
     * @param array<int, UploadedFile> $fotos
     * @return array<int, string>
     */
    public static function guardar(SolicitudServicio $s, array $fotos, string $tipo, ?int $usuId): array
    {
        $carpeta = config('servicio.carpeta_adjuntos') . '/' . $s->sse_id;
        $rutas   = [];

        foreach ($fotos as $foto) {
            $ruta    = $foto->store($carpeta, 'public');
            $rutas[] = $ruta;

            SolicitudAdjunto::create([
                'sad_sseid'  => $s->sse_id,
                'sad_archiv' => $ruta,
                'sad_nombre' => mb_substr($foto->getClientOriginalName(), 0, 150),
                'sad_tipo'   => $tipo,
                'sad_usuid'  => $usuId,
            ]);
        }

        return $rutas;
    }
}

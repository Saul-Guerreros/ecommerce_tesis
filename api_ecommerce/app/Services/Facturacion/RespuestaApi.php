<?php

namespace App\Services\Facturacion;

/**
 * Resultado de una llamada a Facturacion_API, ya separado del transporte
 * HTTP: el servicio de negocio no toca Guzzle ni cabeceras.
 *
 * $status === null significa "no hubo respuesta" (corte de red o tiempo
 * agotado). Es un caso distinto de un error 4xx/5xx: el comprobante PUDO
 * haberse emitido igual, asi que nunca se trata como un rechazo.
 */
final class RespuestaApi
{
    /** @param array<string, mixed> $cuerpo */
    public function __construct(
        public readonly ?int $status,
        public readonly array $cuerpo = [],
        public readonly ?string $fallaRed = null,
    ) {}

    public function sinRespuesta(): bool
    {
        return $this->status === null;
    }

    public function dato(string $clave, mixed $defecto = null): mixed
    {
        return $this->cuerpo[$clave] ?? $defecto;
    }

    /** Codigo corto del error de la API (datos_invalidos, numero_usado...). */
    public function error(): ?string
    {
        $error = $this->cuerpo['error'] ?? null;

        return is_string($error) ? $error : null;
    }

    /** APROBADO | RECHAZADO | PENDIENTE | PREPARADO, segun el comprobante. */
    public function estado(): ?string
    {
        $estado = $this->cuerpo['estado'] ?? null;

        return is_string($estado) ? $estado : null;
    }
}
<?php

namespace App\Services\Chat;

/** Lo que se acumula durante UN turno: tarjetas, acciones y métricas. */
final class AcumuladorTurno
{
    private const MAX_TARJETAS = 6;
    /** Lo que se le envió al modelo en cada herramienta: insumo del rescate. */
    public array $resultados = [];
    public bool $rescatado = false;

    /** @var array<int, array> pub_id => tarjeta (sin duplicados, en orden de aparición) */
    private array $tarjetas = [];
    private array $acciones = [];

    public bool $derivada = false;
    /** Se consultó el catálogo en este turno (insumo de la métrica "sin respuesta"). */
    private bool $huboBusqueda = false;
    private bool $huboError = false;
    public ?string $modelo = null;
    public int $vueltas = 0;
    public int $tokensEntrada = 0;
    public int $tokensSalida = 0;

    public function registrarRespuestaModelo(array $respuesta): void
    {
        $this->modelo = $respuesta['modelo'];
        $this->vueltas++;
        $this->tokensEntrada += $respuesta['uso']['entrada'];
        $this->tokensSalida += $respuesta['uso']['salida'];
    }

    public function registrarHerramienta(string $nombre, ResultadoHerramienta $resultado): void
    {
        $this->resultados[] = ['herramienta' => $nombre, 'datos' => $resultado->paraModelo];
        foreach ($resultado->tarjetas as $tarjeta) {
            $this->tarjetas[$tarjeta['pub_id']] ??= $tarjeta;
        }

        array_push($this->acciones, ...$resultado->acciones);
        $this->derivada = $this->derivada || $resultado->motivoDerivacion !== null;

        if (in_array($nombre, ['buscar_productos', 'ver_producto'], true)) {
            $this->huboBusqueda = true;
        }

        if (isset($resultado->paraModelo['error'])) {
            $this->huboError = true;
        }
    }

    public function tarjetas(): array
    {
        return array_slice(array_values($this->tarjetas), 0, self::MAX_TARJETAS);
    }

    public function acciones(): array
    {
        return $this->acciones;
    }

    /**
     * El turno no pudo responder: buscó (o falló una herramienta) y no
     * mostró ningún producto. Las derivadas se excluyen porque se miden
     * aparte (cnv_estado = 'derivada').
     */
    public function sinRespuesta(): bool
    {
        return ! $this->derivada
            && ($this->huboBusqueda || $this->huboError)
            && $this->tarjetas === [];
    }

    /** id + título de lo mostrado: se guarda para reconstruir el historial. */
    public function productosMostrados(): array
    {
        return array_map(fn ($t) => ['id' => $t['pub_id'], 'titulo' => $t['titulo']], $this->tarjetas());
    }
}
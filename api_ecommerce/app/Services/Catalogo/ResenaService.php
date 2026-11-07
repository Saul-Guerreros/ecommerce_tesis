<?php

namespace App\Services\Catalogo;

use App\Models\Catalogo\Resena;
use App\Models\Cliente;
use App\Models\Pedido\DetallePedido;
use App\Models\Pedido\EstadoPedido;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio del módulo de reseñas.
 *
 *  - Solo opina quien compró y RECIBIÓ el producto (pedido ENTREGADO).
 *  - Una reseña por cliente y por modelo base (compartida entre variantes).
 *  - Publicación inmediata; el administrador puede ocultarla después.
 *  - Todo cambio recalcula el promedio desnormalizado en publicacion_web.
 */
class ResenaService
{
    public const POR_PAGINA = 5;
    public const ORDENES = ['recientes', 'mejor', 'peor'];

    // ---------------------------------------------------------------
    // Grupo (modelo base + variantes)
    // ---------------------------------------------------------------

    public function grupoDe(Producto $producto): int
    {
        return (int) ($producto->prd_padre_id ?? $producto->prd_id);
    }

    /** @return int[] */
    public function idsDelGrupo(int $grupo): array
    {
        return Producto::query()
            ->where('prd_id', $grupo)
            ->orWhere('prd_padre_id', $grupo)
            ->pluck('prd_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ---------------------------------------------------------------
    // Elegibilidad
    // ---------------------------------------------------------------

    /**
     * Línea del pedido ENTREGADO más reciente del cliente que contiene algún
     * producto del grupo. Es la prueba de "compra verificada".
     */
    public function compraQueHabilita(Cliente $cliente, int $grupo): ?DetallePedido
    {
        return DetallePedido::query()
            ->whereIn('dpd_prdid', $this->idsDelGrupo($grupo))
            ->whereHas('pedido', fn ($q) => $q
                ->where('ped_cliid', $cliente->cli_id)
                ->whereHas('estadoPedido', fn ($e) => $e->where('est_codigo', EstadoPedido::ENTREGADO)))
            ->orderByDesc('dpd_pedid')
            ->first();
    }

    public function resenaDelCliente(Cliente $cliente, int $grupo): ?Resena
    {
        return Resena::where('res_cliid', $cliente->cli_id)
            ->where('res_grpid', $grupo)
            ->first();
    }

    // ---------------------------------------------------------------
    // Escritura (cliente)
    // ---------------------------------------------------------------

    public function crear(Cliente $cliente, Producto $producto, array $datos): Resena
    {
        $grupo  = $this->grupoDe($producto);
        $compra = $this->compraQueHabilita($cliente, $grupo);

        if (! $compra) {
            throw ValidationException::withMessages([
                'res_califi' => 'Solo podés reseñar productos de pedidos que ya recibiste.',
            ]);
        }

        if ($this->resenaDelCliente($cliente, $grupo)) {
            throw ValidationException::withMessages([
                'res_califi' => 'Ya escribiste una reseña de este producto. Podés editarla.',
            ]);
        }

        try {
            return DB::transaction(function () use ($cliente, $compra, $grupo, $datos) {
                $resena = Resena::create([
                    'res_prdid'  => $compra->dpd_prdid,
                    'res_grpid'  => $grupo,
                    'res_cliid'  => $cliente->cli_id,
                    'res_pedid'  => $compra->dpd_pedid,
                    'res_califi' => $datos['res_califi'],
                    'res_titulo' => $datos['res_titulo'] ?? null,
                    'res_coment' => $datos['res_coment'],
                ]);

                $this->recalcular($grupo);

                return $resena;
            });
        } catch (QueryException $e) {
            // Doble clic / dos pestañas: la UNIQUE de PostgreSQL frena el duplicado.
            if ($e->getCode() === '23505') {
                throw ValidationException::withMessages([
                    'res_califi' => 'Ya escribiste una reseña de este producto. Podés editarla.',
                ]);
            }
            throw $e;
        }
    }

    public function actualizar(Resena $resena, array $datos): Resena
    {
        return DB::transaction(function () use ($resena, $datos) {
            $resena->fill([
                'res_califi' => $datos['res_califi'],
                'res_titulo' => $datos['res_titulo'] ?? null,
                'res_coment' => $datos['res_coment'],
            ]);
            // Una reseña oculta sigue oculta aunque se edite: si no, editar
            // sería una forma de saltarse la moderación.
            $resena->res_editad = true;
            $resena->save();

            $this->recalcular($resena->res_grpid);

            return $resena;
        });
    }

    public function eliminar(Resena $resena): void
    {
        DB::transaction(function () use ($resena) {
            $grupo = $resena->res_grpid;
            $resena->delete();
            $this->recalcular($grupo);
        });
    }

    // ---------------------------------------------------------------
    // Moderación (admin)
    // ---------------------------------------------------------------

    public function cambiarVisibilidad(Resena $resena, ?string $motivo): Resena
    {
        return DB::transaction(function () use ($resena, $motivo) {
            if ($resena->esVisible()) {
                $resena->res_estado = Resena::OCULTA;
                $resena->res_motocu = $motivo;
            } else {
                $resena->res_estado = Resena::VISIBLE;
                $resena->res_motocu = null;
            }
            $resena->save();

            $this->recalcular($resena->res_grpid);

            return $resena;
        });
    }

    /** Texto vacío = quitar la respuesta. */
    public function responder(Resena $resena, ?string $texto, Usuario $usuario): Resena
    {
        $texto = $texto !== null && trim($texto) !== '' ? $texto : null;

        $resena->res_respue = $texto;
        $resena->res_fecres = $texto ? now() : null;
        $resena->res_usures = $texto ? $usuario->usu_id : null;
        $resena->save();

        return $resena;
    }

    // ---------------------------------------------------------------
    // Lectura
    // ---------------------------------------------------------------

    public function listarPublicas(int $grupo, string $orden, ?int $estrellas): LengthAwarePaginator
    {
        $query = Resena::visibles()
            ->where('res_grpid', $grupo)
            ->with(['cliente.persona', 'producto.publicacionWeb']);

        if ($estrellas) {
            $query->where('res_califi', $estrellas);
        }

        match ($orden) {
            'mejor' => $query->orderByDesc('res_califi')->orderByDesc('res_creado'),
            'peor'  => $query->orderBy('res_califi')->orderByDesc('res_creado'),
            default => $query->orderByDesc('res_creado'),
        };

        return $query->paginate(self::POR_PAGINA);
    }

    /** @return array{promedio: float|null, total: int, distribucion: array<int,int>} */
    public function resumen(int $grupo): array
    {
        $conteos = Resena::visibles()
            ->where('res_grpid', $grupo)
            ->selectRaw('res_califi, COUNT(*) AS cantidad')
            ->groupBy('res_califi')
            ->pluck('cantidad', 'res_califi');

        $distribucion = [];
        $total = 0;
        $suma  = 0;

        foreach ([5, 4, 3, 2, 1] as $estrella) {
            $cantidad = (int) ($conteos[$estrella] ?? 0);
            $distribucion[$estrella] = $cantidad;
            $total += $cantidad;
            $suma  += $cantidad * $estrella;
        }

        return [
            'promedio'     => $total > 0 ? round($suma / $total, 1) : null,
            'total'        => $total,
            'distribucion' => $distribucion,
        ];
    }

    /**
     * Copia promedio y cantidad de reseñas VISIBLES a la ficha web de todas
     * las variantes del grupo (todas muestran el mismo rating).
     */
    public function recalcular(int $grupo): void
    {
        $resumen = $this->resumen($grupo);

        PublicacionWeb::query()
            ->whereIn('pub_prdid', $this->idsDelGrupo($grupo))
            ->update([
                'pub_rating' => $resumen['promedio'],
                'pub_nresen' => $resumen['total'],
            ]);
    }

    /** "Juan P.": nunca se publica el nombre completo del cliente. */
    public static function nombrePublico(?Persona $persona): string
    {
        if (! $persona || ! $persona->per_nombr1) {
            return 'Cliente';
        }

        $nombre   = mb_convert_case(mb_strtolower(trim($persona->per_nombr1)), MB_CASE_TITLE);
        $apellido = trim((string) $persona->per_apell1);

        return $apellido !== ''
            ? $nombre . ' ' . mb_strtoupper(mb_substr($apellido, 0, 1)) . '.'
            : $nombre;
    }
}
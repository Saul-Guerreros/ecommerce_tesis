<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Construye la consulta de la bandeja de pedidos del Panel Admin.
 *
 * Vive en un Service y no en el controlador porque la MISMA consulta
 * alimenta dos salidas: el listado paginado (index) y la exportacion
 * (exportar). Si la logica estuviera en el controlador, el archivo
 * exportado podria dejar de coincidir con lo que el administrador ve
 * en pantalla, que es justamente lo que exige la instruccion.
 */
class PedidoConsultaService
{
    /**
     * Eager loading explicito (regla transversal del estandar: evitar N+1).
     * La fila del listado necesita la coleccion completa de pagos para
     * calcular "1/2 verificados" sin disparar una consulta por pedido.
     */
    public const RELACIONES = [
        'cliente.persona',
        'tipoEntrega',
        'estadoPedido',
        'estadoPago',
        'metodoPago',
        'pagos.metodoPago',
        'pagos.estadoPago',
        'pagos.comprobante',
    ];

    /**
     * Expresion SQL que arma el nombre completo de la persona sin tildes y
     * en minuscula. translate() no depende de la extension unaccent, que
     * puede no estar instalada en el PostgreSQL del servidor de la tesis.
     */
    private const EXPR_NOMBRE = "translate(lower(
        coalesce(per_nombr1, '') || ' ' || coalesce(per_nombr2, '') || ' ' ||
        coalesce(per_apell1, '') || ' ' || coalesce(per_apell2, '')
    ), 'áàäâéèëêíìïîóòöôúùüûñç', 'aaaaeeeeiiiioooouuuunc')";

    public function query(array $filtros): Builder
    {
        $query = Pedido::query()->with(self::RELACIONES);

        $this->aplicarBusqueda($query, $filtros);
        $this->aplicarFiltros($query, $filtros);
        $this->aplicarRangoFechas($query, $filtros);

        return $this->aplicarOrden($query);
    }

    /**
     * Cantidad de PAGOS (no de pedidos) que esperan verificacion.
     * Un pedido con pago dividido y dos comprobantes sin revisar cuenta 2.
     * Se excluyen los pedidos cancelados: su pago ya no se va a verificar.
     */
    public function pagosPendientes(): int
    {
        return PedidoPago::query()
            ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', EstadoPago::PENDIENTE))
            ->whereHas('pedido.estadoPedido', fn ($q) => $q->where('est_codigo', '!=', EstadoPedido::CANCELADO))
            ->count();
    }

    // ---------- Busqueda (excluyente: un solo criterio a la vez) ----------

    private function aplicarBusqueda(Builder $query, array $f): void
    {
        if ($numero = trim((string) ($f['num'] ?? ''))) {
            // Parcial y case-insensitive: "001047" encuentra PED-2026-001047.
            $query->where('ped_numero', 'ILIKE', '%' . $numero . '%');

            return;
        }

        if ($cedula = $this->soloDigitos((string) ($f['ci'] ?? ''))) {
            // Se normalizan AMBOS lados: el termino en PHP y la columna en SQL.
            // Asi "1.234.567" encuentra al cliente guardado como "1234567" y
            // los ceros a la izquierda no se pierden (nunca se castea a int).
            $query->whereHas('cliente.persona', fn ($p) => $p->whereRaw(
                "regexp_replace(coalesce(per_cedula, ''), '\\D', '', 'g') LIKE ?",
                ['%' . $cedula . '%']
            ));

            return;
        }

        if ($nombre = $this->normalizarTexto((string) ($f['cliente'] ?? ''))) {
            // Busca sobre el nombre completo concatenado: "juan perez"
            // encuentra al cliente aunque el termino cruce nombre y apellido.
            $query->whereHas('cliente.persona', fn ($p) => $p->whereRaw(
                self::EXPR_NOMBRE . ' LIKE ?',
                ['%' . $nombre . '%']
            ));
        }
    }

    // ---------- Filtros ----------

    private function aplicarFiltros(Builder $query, array $f): void
    {
        if (!empty($f['estado_pedido'])) {
            $query->where('ped_estid', (int) $f['estado_pedido']);
        }

        if (!empty($f['estado_pago'])) {
            $query->where('ped_epaid', (int) $f['estado_pago']);
        }

        if (!empty($f['tipo_entrega'])) {
            $query->where('ped_tenid', (int) $f['tipo_entrega']);
        }

        // Metodo de pago: con pago dividido pedido.ped_mpaid es NULL, por lo
        // que filtrar por esa columna perderia esos pedidos. pedido_pago en
        // cambio SIEMPRE tiene una fila por pago, incluso cuando es uno solo.
        // whereHas usa EXISTS, asi que no duplica filas ni rompe el paginado.
        if (!empty($f['metodo_pago'])) {
            $query->whereHas('pagos', fn ($p) => $p->where('ppa_mpaid', (int) $f['metodo_pago']));
        }
    }

    // ---------- Rango de fechas ----------

    private function aplicarRangoFechas(Builder $query, array $f): void
    {
        $zona = config('app.timezone'); // America/Asuncion

        if (!empty($f['desde'])) {
            $query->where('ped_creado', '>=', Carbon::parse($f['desde'], $zona)->startOfDay());
        }

        if (!empty($f['hasta'])) {
            // endOfDay para incluir TODOS los pedidos del dia final,
            // sin importar la hora de creacion.
            $query->where('ped_creado', '<=', Carbon::parse($f['hasta'], $zona)->endOfDay());
        }
    }

    // ---------- Orden ----------

    /**
     * La bandeja es una cola de trabajo, no un log cronologico: los pedidos
     * que necesitan accion del administrador (pago pendiente, parcial o
     * rechazado) van arriba. Dentro de cada grupo, el mas reciente primero.
     */
    private function aplicarOrden(Builder $query): Builder
    {
        return $query
            ->orderByRaw("(
                SELECT CASE WHEN ep.epa_codigo IN (?, ?, ?) THEN 0 ELSE 1 END
                FROM estado_pago ep
                WHERE ep.epa_id = pedido.ped_epaid
            ) ASC", [EstadoPago::PENDIENTE, EstadoPago::PARCIAL, EstadoPago::RECHAZADO])
            ->orderByDesc('ped_creado');
    }

    // ---------- Normalizacion ----------

    private function soloDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    private function normalizarTexto(string $valor): string
    {
        $limpio = preg_replace('/\s+/u', ' ', trim($valor)) ?? '';

        $sinTildes = strtr(mb_strtolower($limpio, 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);

        return $sinTildes;
    }
}
<?php

namespace App\Services\Pedido;

use App\Models\Cliente;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Mis Pedidos": consulta de pedidos del lado cliente.
 *
 * Es el equivalente de PedidoConsultaService (bandeja del Panel Admin),
 * pero con un alcance mucho más chico a propósito:
 *
 *   - Siempre filtrado por ped_cliid = el cliente autenticado. No hay
 *     parámetro que pueda ampliar ese alcance: un cliente nunca pasa un
 *     ID de cliente por query string.
 *   - Sin búsqueda por nombre/cédula (es obvio quién está mirando).
 *   - El filtro de estado es una pestaña fija (ver ESTADOS_FILTRO), no un
 *     combo abierto contra el catálogo completo: el cliente no necesita
 *     distinguir EN_PREPARACION de LISTO con la granularidad del admin.
 */
class PedidoClienteService
{
    public const RELACIONES = [
        'tipoEntrega',
        'estadoPedido',
        'estadoPago',
        'metodoPago',
        'pagos.metodoPago',
        'pagos.estadoPago',
        'pagos.comprobante',
        // El cliente necesita ver POR QUÉ se le rechazó un pago; sin esto
        // sólo vería "Pago rechazado" y volvería a subir el mismo archivo.
        'pagos.motivoRechazo',
    ];

    public const FILTRO_TODOS = 'todos';
    public const FILTRO_PENDIENTE_PAGO = 'pendiente_pago';
    public const FILTRO_EN_PROCESO = 'en_proceso';
    public const FILTRO_ENTREGADOS = 'entregados';
    public const FILTRO_CANCELADOS = 'cancelados';

    public function query(Cliente $cliente, string $filtro = self::FILTRO_TODOS): Builder
    {
        $query = Pedido::query()
            ->with(self::RELACIONES)
            ->where('ped_cliid', $cliente->cli_id);

        $this->aplicarFiltro($query, $filtro);

        return $query->orderByDesc('ped_creado');
    }

    private function aplicarFiltro(Builder $query, string $filtro): void
    {
        match ($filtro) {
            // conPagoExpirable() (no pendienteDePago()) a propósito: esta
            // pestaña es donde el cliente tiene algo pendiente de SU
            // lado — declarar el pago por primera vez, o reemplazar un
            // comprobante rechazado. RECHAZADO queda afuera de EN_PROCESO
            // más abajo precisamente para que no termine repartido entre
            // las dos pestañas.
            self::FILTRO_PENDIENTE_PAGO => $query->conPagoExpirable(),

            self::FILTRO_EN_PROCESO => $query
                ->whereHas('estadoPago', fn ($q) => $q->whereIn('epa_codigo', [
                    EstadoPago::PAGO_VERIFICAR, EstadoPago::CONFIRMADO, EstadoPago::PARCIAL,
                ]))
                ->whereHas('estadoPedido', fn ($q) => $q->whereNotIn('est_codigo', [
                    EstadoPedido::ENTREGADO, EstadoPedido::CANCELADO,
                ])),

            self::FILTRO_ENTREGADOS => $query
                ->whereHas('estadoPedido', fn ($q) => $q->where('est_codigo', EstadoPedido::ENTREGADO)),

            self::FILTRO_CANCELADOS => $query
                ->whereHas('estadoPedido', fn ($q) => $q->where('est_codigo', EstadoPedido::CANCELADO)),

            default => null, // 'todos': sin filtro adicional
        };
    }

    /**
     * Un único pedido del cliente, con todo lo necesario para el detalle.
     * 404 si el número no existe O si pertenece a otro cliente: la
     * respuesta es la misma a propósito, para no confirmarle a nadie que
     * un número de pedido ajeno existe.
     */
    public function porNumero(Cliente $cliente, string $numero): Pedido
    {
        return Pedido::with([
            ...self::RELACIONES,
            'entrega.sucursal',
            'facturacion',
            'detalles.producto.publicacionWeb.imagenes',
        ])
            ->where('ped_cliid', $cliente->cli_id)
            ->where('ped_numero', $numero)
            ->firstOrFail();
    }

    /**
     * Para el aviso de "Pedidos pendientes de pago": el punto rojo sobre
     * el ícono de perfil, la tarjeta flotante de la Home y el contador del
     * dropdown. Devuelve TODOS los pendientes, no sólo el más reciente —
     * un cliente puede abandonar el checkout más de una vez.
     *
     * conPagoExpirable() (no pendienteDePago()) a propósito: cubre tanto
     * el checkout abandonado como el pedido con un comprobante rechazado
     * que todavía puede reemplazar. Los dos casos tienen plazo corriendo
     * y los dos requieren que el cliente vuelva a actuar — el aviso tiene
     * que alcanzarlos a ambos o el barrido puede cancelar un pedido sin
     * que el cliente se haya enterado nunca de que tenía que hacer algo.
     *
     * Ordenado por el que vence antes primero: es el más urgente, así que
     * es el que conviene mostrar arriba de la lista.
     */
    public function pendientes(Cliente $cliente)
    {
        return Pedido::with('estadoPago', 'detalles.producto.publicacionWeb.imagenes')
            ->where('ped_cliid', $cliente->cli_id)
            ->conPagoExpirable()
            ->whereNotNull('ped_vence')
            ->where('ped_vence', '>', now())
            ->orderBy('ped_vence')
            ->get();
    }
}
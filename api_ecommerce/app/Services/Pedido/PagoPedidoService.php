<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoComprobante;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Paso 3 del checkout: el cliente declara su pago sobre un pedido que ya
 * existe y que ya tiene el stock reservado a su nombre.
 *
 * Reemplaza a CheckoutPagoService, que escribía el pago como borrador en
 * pedido_temporal.ptm_pago. Tres diferencias de fondo:
 *
 *   1. El total ya no se recalcula contra el carrito: quedó congelado en
 *      pedido.ped_total al confirmar. Antes había que revalidar los montos
 *      en cada paso porque el carrito seguía vivo y podía cambiar debajo.
 *   2. Los comprobantes van directo a su carpeta definitiva. Ya no existe
 *      comprobantes_temp ni el baile de archivos huérfanos.
 *   3. Declarar el pago cierra el plazo: ped_vence pasa a NULL y el pedido
 *      sale del barrido de expiración.
 */
class PagoPedidoService
{
    public const MODO_UNICO = 'unico';
    public const MODO_DIVIDIDO = 'dividido';

    private const CARPETA = 'comprobantes';

    public function __construct(
        private CheckoutPagoService $configuracion,
        private EstadoPagoPedidoService $estadoPago,
    ) {}

    // ---------- Lectura ----------

    /**
     * Datos de pago de la tienda (banco, Tigo, mensaje de efectivo) y
     * métodos activos. Se reutiliza tal cual del service anterior: esa
     * parte no cambió.
     */
    public function datosIniciales(): array
    {
        return $this->configuracion->datosIniciales();
    }

    /**
     * Pedido del cliente autenticado que todavía puede pagarse.
     *
     * Es el guard del Paso 3: valida propiedad, estado y vencimiento en un
     * solo lugar, para que ni la pantalla de pago ni el POST puedan operar
     * sobre un pedido ajeno, ya pagado o expirado.
     */
    public function pedidoPagable(Usuario $usuario, string $numero): Pedido
    {
        $pedido = $this->pedidoDelCliente($usuario, $numero);

        if (!$pedido->pendienteDePago()) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido ya tiene un pago registrado.',
            ]);
        }

        if ($pedido->vencido()) {
            throw ValidationException::withMessages([
                'pedido' => 'El plazo para pagar este pedido venció y la reserva fue liberada.',
            ]);
        }

        return $pedido;
    }

    /**
     * Pedido del cliente con el pago ya declarado: alimenta la pantalla de
     * resultado (los 3 casos). No exige que el pago esté confirmado, sólo
     * que exista.
     */
    public function pedidoConPago(Usuario $usuario, string $numero): Pedido
    {
        $pedido = $this->pedidoDelCliente($usuario, $numero);

        if ($pedido->pendienteDePago()) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido todavía no tiene un pago registrado.',
            ]);
        }

        $pedido->load([
            'pagos.metodoPago', 'pagos.comprobante',
            'entrega.sucursal', 'tipoEntrega', 'estadoPago', 'estadoPedido',
        ]);

        return $pedido;
    }

    // ---------- Escritura ----------

    /**
     * @param  array  $datos  $request->validated()
     */
    public function registrar(Usuario $usuario, string $numero, array $datos): Pedido
    {
        $esDividido = ($datos['modo'] ?? self::MODO_UNICO) === self::MODO_DIVIDIDO;

        // Se guardan fuera de la transacción: Storage no participa del
        // rollback. Si la transacción falla, se borran a mano más abajo.
        $archivos = [];

        try {
            return DB::transaction(function () use ($usuario, $numero, $datos, $esDividido, &$archivos) {
                // Se vuelve a resolver DENTRO de la transacción y con lock:
                // dos envíos simultáneos del mismo formulario (doble clic,
                // reintento del navegador) se serializan y el segundo
                // encuentra el pedido ya pagado.
                $pedido = $this->pedidoPagable($usuario, $numero);
                $pedido = Pedido::whereKey($pedido->ped_id)->lockForUpdate()->firstOrFail();

                abort_if($pedido->pagos()->exists(), 409, 'Este pedido ya tiene un pago registrado.');

                $total = (int) round((float) $pedido->ped_total);

                $pagos = $esDividido
                    ? $this->armarDividido($datos['pagos'] ?? [], $total, $archivos)
                    : $this->armarUnico($datos, $total, $archivos);

                $this->crearPagos($pedido, $pagos);
                $this->marcarPedidoPagado($pedido, $pagos, $usuario);

                return $pedido->fresh([
                    'pagos.metodoPago', 'pagos.comprobante',
                    'entrega.sucursal', 'tipoEntrega', 'estadoPago', 'estadoPedido',
                ]);
            });
        } catch (\Throwable $e) {
            foreach ($archivos as $ruta) {
                Storage::disk('public')->delete($ruta);
            }

            throw $e;
        }
    }

    /**
     * El cliente sube un comprobante nuevo para un pago que el admin
     * rechazó.
     *
     * Es la contraparte obligatoria del rechazo: sin esto, rechazar un
     * pago deja el pedido muerto para el cliente (no puede corregir
     * nada) y la reserva termina expirando aunque la intención fuera
     * darle otra oportunidad.
     *
     * Opera sobre UN pago puntual, no sobre el pedido entero, porque eso
     * es lo que se rechaza: con pago dividido, el cliente corrige sólo el
     * comprobante inválido y el otro pago confirmado queda intacto.
     */
    public function reemplazarComprobante(
        Usuario $usuario,
        string $numero,
        int $pagoId,
        UploadedFile $archivo,
    ): Pedido {
        $rutaNueva   = null;
        $rutaAnterior = null;

        try {
            $pedido = DB::transaction(function () use (
                $usuario, $numero, $pagoId, $archivo, &$rutaNueva, &$rutaAnterior
            ) {
                $pedido = $this->pedidoDelCliente($usuario, $numero);
                $pedido = Pedido::whereKey($pedido->ped_id)
                    ->lockForUpdate()
                    ->with('estadoPago', 'estadoPedido')
                    ->firstOrFail();

                $pago = $this->pagoRechazable($pedido, $pagoId);

                $rutaAnterior = $pago->comprobante?->pco_archiv;
                $rutaNueva    = $archivo->store(self::CARPETA, 'public');

                $verificar = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR);

                // El pago vuelve a cero: se limpian el motivo, la
                // observación y los datos de la verificación anterior. Si
                // quedaran, el admin vería el rechazo viejo junto al
                // comprobante nuevo y no sabría cuál mira.
                $pago->update([
                    'ppa_epaid'   => $verificar->epa_id,
                    'ppa_mrpid'   => null,
                    'ppa_observ'  => null,
                    'ppa_montver' => null,
                    'ppa_usuver'  => null,
                    'ppa_fechver' => null,
                ]);

                if ($pago->comprobante) {
                    $pago->comprobante->update([
                        'pco_archiv' => $rutaNueva,
                        'pco_verifi' => false,
                    ]);
                } else {
                    PagoComprobante::create([
                        'pco_pedid'  => $pedido->ped_id,
                        'pco_ppaid'  => $pago->ppa_id,
                        'pco_archiv' => $rutaNueva,
                        'pco_verifi' => false,
                    ]);
                }

                PedidoHistorialEstado::create([
                    'peh_pedid'  => $pedido->ped_id,
                    'peh_usuid'  => $usuario->usu_id,
                    'peh_tipo'   => 'PAGO',
                    'peh_estant' => $pedido->ped_epaid,
                    'peh_estid'  => $verificar->epa_id,
                    'peh_coment' => "Cliente cargó un comprobante nuevo para el Pago {$pago->ppa_orden}",
                    'peh_ip'     => request()->ip(),
                ]);

                // El pedido vuelve a la bandeja de verificación y sale del
                // barrido: ya no está esperando una acción del cliente.
                $this->estadoPago->recalcular($pedido);
                $this->estadoPago->cerrarPlazo($pedido);

                return $pedido->fresh([
                    'pagos.metodoPago', 'pagos.comprobante', 'pagos.estadoPago',
                    'entrega.sucursal', 'tipoEntrega', 'estadoPago', 'estadoPedido',
                ]);
            });
        } catch (\Throwable $e) {
            // Storage no participa del rollback: si la transacción falló,
            // el archivo nuevo quedaría huérfano en disco.
            if ($rutaNueva) {
                Storage::disk('public')->delete($rutaNueva);
            }

            throw $e;
        }

        // Recién con la transacción confirmada se borra el comprobante
        // viejo. Hacerlo antes dejaría al cliente sin ninguno de los dos
        // si algo fallaba después.
        if ($rutaAnterior && $rutaAnterior !== $rutaNueva) {
            Storage::disk('public')->delete($rutaAnterior);
        }

        return $pedido;
    }

    /**
     * Guard del reemplazo. Son cuatro condiciones y ninguna es opcional:
     *
     *   - el pago pertenece a este pedido (404 si no);
     *   - está RECHAZADO (no se puede pisar un comprobante que el admin
     *     todavía no miró, ni uno ya confirmado);
     *   - el pedido no está cancelado;
     *   - el plazo reabierto por el rechazo sigue vigente. Vencido, la
     *     reserva ya se liberó o está por liberarse: aceptar el archivo
     *     le haría creer al cliente que su pedido sigue en pie.
     */
    private function pagoRechazable(Pedido $pedido, int $pagoId): PedidoPago
    {
        $pago = PedidoPago::where('ppa_id', $pagoId)
            ->where('ppa_pedid', $pedido->ped_id)
            ->lockForUpdate()
            ->first();

        abort_if(!$pago, 404, 'El pago no pertenece a este pedido.');

        $pago->load(['estadoPago', 'metodoPago', 'comprobante']);

        if ($pedido->estadoPedido?->est_codigo === EstadoPedido::CANCELADO) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido fue cancelado: ya no admite comprobantes.',
            ]);
        }

        if ($pago->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO) {
            throw ValidationException::withMessages([
                'pago' => 'Sólo se puede reemplazar el comprobante de un pago rechazado.',
            ]);
        }

        if (!$pago->metodoPago?->mpa_requcom) {
            throw ValidationException::withMessages([
                'pago' => 'Este método de pago no lleva comprobante.',
            ]);
        }

        if ($pedido->vencido()) {
            throw ValidationException::withMessages([
                'pedido' => 'El plazo para corregir el comprobante venció y la reserva fue liberada.',
            ]);
        }

        return $pago;
    }

    // ---------- Armado de los pagos ----------

    private function armarUnico(array $datos, int $total, array &$archivos): array
    {
        $metodo      = MetodoPago::porCodigo($datos['mpa_codigo']);
        $comprobante = $datos['comprobante'] ?? null;

        return [[
            'metodo'      => $metodo,
            'monto'       => $total,
            'comprobante' => $metodo->mpa_requcom
                ? $this->guardarArchivo($comprobante, $archivos)
                : null,
        ]];
    }

    private function armarDividido(array $pagos, int $total, array &$archivos): array
    {
        $this->verificarMontos($pagos, $total);

        $resultado = [];

        foreach (array_values($pagos) as $p) {
            $metodo = MetodoPago::porCodigo($p['mpa_codigo']);

            $resultado[] = [
                'metodo'      => $metodo,
                'monto'       => (int) $p['monto'],
                // Regla: el efectivo contra entrega no lleva comprobante.
                'comprobante' => $metodo->mpa_requcom
                    ? $this->guardarArchivo($p['comprobante'] ?? null, $archivos)
                    : null,
            ];
        }

        return $resultado;
    }

    /**
     * La suma de los pagos tiene que cubrir exactamente el total del
     * pedido. El monto nunca se toma como válido por venir del cliente: se
     * compara contra ped_total, que lo escribió el backend al confirmar.
     */
    private function verificarMontos(array $pagos, int $total): void
    {
        $asignado = array_sum(array_map(fn ($p) => (int) ($p['monto'] ?? 0), $pagos));

        if ($asignado > $total) {
            throw ValidationException::withMessages([
                'pagos' => 'El total asignado (Gs. ' . $this->gs($asignado) . ') supera el total del pedido (Gs. '
                    . $this->gs($total) . ').',
            ]);
        }

        if ($asignado < $total) {
            throw ValidationException::withMessages([
                'pagos' => 'Todavía queda un pendiente de Gs. ' . $this->gs($total - $asignado)
                    . '. Asigná el total del pedido para continuar.',
            ]);
        }
    }

    private function guardarArchivo(?UploadedFile $archivo, array &$archivos): ?string
    {
        if (!$archivo instanceof UploadedFile) {
            return null;
        }

        $ruta = $archivo->store(self::CARPETA, 'public');
        $archivos[] = $ruta;

        return $ruta;
    }

    // ---------- Persistencia ----------

    /**
     * Una fila de pedido_pago por cada pago declarado, siempre: con un solo
     * método se escribe una sola fila. Así el panel admin, "Mis pedidos" y
     * los reportes leen un único lugar.
     */
    private function crearPagos(Pedido $pedido, array $pagos): void
    {
        $pendiente = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id;

        foreach ($pagos as $i => $p) {
            $pedidoPago = PedidoPago::create([
                'ppa_pedid' => $pedido->ped_id,
                'ppa_orden' => $i + 1,
                'ppa_mpaid' => $p['metodo']->mpa_id,
                'ppa_monto' => $p['monto'],
                'ppa_epaid' => $pendiente,
            ]);

            if ($p['comprobante']) {
                PagoComprobante::create([
                    'pco_pedid'  => $pedido->ped_id,
                    'pco_ppaid'  => $pedidoPago->ppa_id,
                    'pco_archiv' => $p['comprobante'],
                    'pco_verifi' => false,
                ]);
            }
        }
    }

    /**
     * El pedido sale de PENDIENTE_PAGO y entra a la bandeja de
     * verificación del administrador.
     *
     * ped_vence se limpia: con el pago declarado, la reserva deja de tener
     * plazo y el pedido sale del barrido de expiración. Si no se limpiara,
     * una transferencia esperando revisión podría cancelarse sola.
     */
    private function marcarPedidoPagado(Pedido $pedido, array $pagos, Usuario $usuario): void
    {
        $esDividido = count($pagos) > 1;
        $pendiente  = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR);

        $pedido->update([
            // Sólo tiene sentido con un único método; con pago dividido la
            // verdad vive en pedido_pago.
            'ped_mpaid'  => $esDividido ? null : $pagos[0]['metodo']->mpa_id,
            'ped_pagdiv' => $esDividido,
            'ped_epaid'  => $pendiente->epa_id,
            'ped_vence'  => null,
        ]);

        PedidoHistorialEstado::create([
            'peh_pedid'  => $pedido->ped_id,
            'peh_usuid'  => $usuario->usu_id,
            'peh_tipo'   => 'PAGO',
            'peh_estant' => null,
            'peh_estid'  => $pendiente->epa_id,
            'peh_coment' => $esDividido
                ? 'Cliente declaró su pago dividido en ' . count($pagos) . ' pagos'
                : 'Cliente declaró su pago: ' . $pagos[0]['metodo']->mpa_descri,
        ]);
    }

    // ---------- Helpers ----------

    /**
     * Relaciones necesarias para armar las tarjetas de resumen (Productos
     * en Carrito, Datos de Entrega, Datos de Facturación) que acompañan al
     * Paso 3. Se cargan siempre, incluso en pedidoPagable(): esta pantalla
     * las necesita en el primer GET, no sólo después de pagar.
     */
    private function pedidoDelCliente(Usuario $usuario, string $numero): Pedido
    {
        $cliente = $usuario->persona?->cliente;

        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        return Pedido::with([
            'estadoPago', 'estadoPedido', 'tipoEntrega',
            'detalles.producto.publicacionWeb.imagenes',
            'entrega.sucursal', 'facturacion',
        ])
            ->where('ped_numero', $numero)
            ->where('ped_cliid', $cliente->cli_id)
            ->firstOr(fn () => abort(404, 'No encontramos ese pedido en tu cuenta.'));
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}
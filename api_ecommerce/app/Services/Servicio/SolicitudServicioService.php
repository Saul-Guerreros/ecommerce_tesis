<?php

namespace App\Services\Servicio;

use App\Models\Cliente;
use App\Models\Configuracion\ConfiguracionEnvio;
use App\Models\Configuracion\Sucursal;
use App\Models\Configuracion\TarifaEnvio;
use App\Models\Direccion;
use App\Models\Pedido\DetallePedido;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use App\Models\Servicio\EstadoLogistica;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudAdjunto;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\TipoDispositivo;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FASE 0 - Solicitud del cliente desde la tienda (C2) y su consulta (C3/C4).
 */
class SolicitudServicioService
{
    public function __construct(private ServicioEstadoService $estados) {}

    // ---------- Configuración del wizard ----------

    public function configuracion(Usuario $usuario): array
    {
        $persona = $usuario->persona;

        return [
            'tipos_dispositivo' => TipoDispositivo::activos()->orderBy('tdi_descri')
                ->get(['tdi_id', 'tdi_codigo', 'tdi_descri']),
            'sucursales'        => Sucursal::paraRetiro()->with('ciudad')
                ->orderByDesc('suc_princi')->orderBy('suc_nombre')->get(),
            'direcciones'       => $persona->direcciones()->activas()->with('ciudad.departamento')
                ->orderByDesc('dir_predet')->orderByDesc('dir_id')->get(),
            'lineas_garantia'   => $this->lineasEnGarantia($this->clienteDe($usuario)),
            'garantia_dias'     => (int) config('servicio.garantia_compra_dias'),
            'max_fotos'         => (int) config('servicio.max_fotos_cliente'),
        ];
    }

    /**
     * Líneas de compra (productos) con garantía vigente del cliente.
     *
     * Una línea cubre cuando: su pedido está ENTREGADO, el producto se vendió
     * con garantía (dpd_gardia > 0, plazo congelado al vender) y todavía no
     * pasaron dpd_gardia días desde la entrega (pedido.ped_entreg). El último
     * día cuenta completo (mismo criterio que la garantía del trabajo).
     *
     * Es la única fuente de verdad: la usan la lista del wizard y la
     * validación de crear(), para que una pantalla nunca permita lo que la
     * otra bloquea.
     *
     * @return Collection<int, array{dpd_id:int, ped_id:int, ped_numero:?string, producto:string,
     *                               fecha_entrega:string, vence:\Illuminate\Support\Carbon,
     *                               dias_restantes:int, dias_garantia:int}>
     */
    public function lineasEnGarantia(Cliente $cliente): Collection
    {
        return DetallePedido::query()
            ->select([
                'detalle_pedido.dpd_id', 'detalle_pedido.dpd_pedid', 'detalle_pedido.dpd_descri',
                'detalle_pedido.dpd_gardia', 'pedido.ped_numero', 'pedido.ped_entreg',
            ])
            ->join('pedido', 'pedido.ped_id', '=', 'detalle_pedido.dpd_pedid')
            ->join('estado_pedido', 'estado_pedido.est_id', '=', 'pedido.ped_estid')
            ->where('pedido.ped_cliid', $cliente->cli_id)
            ->where('estado_pedido.est_codigo', EstadoPedido::ENTREGADO)
            ->whereNotNull('pedido.ped_entreg')
            ->where('detalle_pedido.dpd_gardia', '>', 0)
            ->whereRaw("pedido.ped_entreg + (detalle_pedido.dpd_gardia * INTERVAL '1 day') >= ?",
                [now()->startOfDay()])
            ->orderByDesc('pedido.ped_entreg')
            ->orderBy('detalle_pedido.dpd_id')
            ->get()
            ->map(function ($l) {
                $entrega = \Illuminate\Support\Carbon::parse($l->ped_entreg);
                $vence   = $entrega->copy()->addDays((int) $l->dpd_gardia)->endOfDay();

                return [
                    'dpd_id'         => (int) $l->dpd_id,
                    'ped_id'         => (int) $l->dpd_pedid,
                    'ped_numero'     => $l->ped_numero,
                    'producto'       => $l->dpd_descri,
                    'fecha_entrega'  => $entrega->format('Y-m-d'),
                    'vence'          => $vence,
                    'dias_restantes' => max(0, (int) now()->startOfDay()->diffInDays($vence->copy()->startOfDay(), false)),
                    'dias_garantia'  => (int) $l->dpd_gardia,
                ];
            })
            ->values();
    }

    /**
     * Costo de retiro a domicilio: la menor tarifa de envío configurada para
     * la ciudad de la dirección (cualquier sucursal habilitada puede retirar).
     * Si la ciudad no tiene tarifa, se usa el costo fijo de envío.
     *
     * @return array{costo: float, suc_id: ?int}
     */
    public function costoRetiro(Usuario $usuario, int $dirId): array
    {
        $direccion = $this->direccionDelCliente($usuario, $dirId);

        $tarifa = $direccion->dir_ciuid
            ? TarifaEnvio::where('tev_ciuid', $direccion->dir_ciuid)
                ->whereHas('sucursal', fn ($q) => $q->where('suc_estado', 1))
                ->orderBy('tev_costo')
                ->first()
            : null;

        if ($tarifa) {
            return ['costo' => (float) $tarifa->tev_costo, 'suc_id' => $tarifa->tev_sucid];
        }

        return ['costo' => (float) (ConfiguracionEnvio::actual()->cfe_costo_fijo ?? 0), 'suc_id' => null];
    }

    // ---------- Alta ----------

    /**
     * @param array<int, UploadedFile> $fotos
     */
    public function crear(Usuario $usuario, array $datos, array $fotos): SolicitudServicio
    {
        $cliente = $this->clienteDe($usuario);

        // Reclamo de garantía de un SERVICIO anterior (Paso 6): el equipo es el
        // mismo, así que sus datos se toman de la solicitud original.
        $original = null;
        if (!empty($datos['sse_sseori'])) {
            $original = $this->servicioReclamable($cliente, $datos['sse_sseori']);
            $datos = array_merge($datos, [
                'sse_garant' => true,
                'sse_pedid'  => null,
                'sse_dpdid'  => null,
                'sse_tdiid'  => $original->sse_tdiid,
                'sse_marca'  => $original->sse_marca,
                'sse_modelo' => $original->sse_modelo,
                'sse_nroser' => $original->sse_nroser,
            ]);
        }

        // Garantía de COMPRA: la línea (producto) debe ser del cliente y estar
        // vigente. El pedido se deduce de la línea, no se confía en el que mande el cliente.
        if (!empty($datos['sse_garant']) && !$original) {
            $linea = $this->lineasEnGarantia($cliente)->firstWhere('dpd_id', (int) ($datos['sse_dpdid'] ?? 0));
            if (!$linea) {
                throw ValidationException::withMessages([
                    'sse_dpdid' => 'El producto seleccionado no está dentro del plazo de garantía.',
                ]);
            }
            $datos['sse_dpdid'] = $linea['dpd_id'];
            $datos['sse_pedid'] = $linea['ped_id'];
        }

        $aDomicilio = $datos['modalidad'] === 'domicilio';
        $retiro     = $aDomicilio ? $this->costoRetiro($usuario, (int) $datos['sse_dirid']) : null;

        $tipoEntrega = TipoEntrega::porCodigo($aDomicilio ? TipoEntrega::ENVIO : TipoEntrega::RETIRO);

        if (!$aDomicilio) {
            Sucursal::paraRetiro()->findOrFail($datos['sse_sucid']);
        }

        $rutasGuardadas = [];

        try {
            return DB::transaction(function () use ($usuario, $cliente, $datos, $fotos, $aDomicilio, $retiro, $tipoEntrega, $original, &$rutasGuardadas) {
                $solicitud = SolicitudServicio::create([
                    'sse_cliid'  => $cliente->cli_id,
                    'sse_tdiid'  => $datos['sse_tdiid'],
                    'sse_marca'  => $datos['sse_marca'],
                    'sse_modelo' => $datos['sse_modelo'],
                    'sse_nroser' => $datos['sse_nroser'] ?? null,
                    'sse_proble' => $datos['sse_proble'],
                    'sse_acceso' => $datos['sse_acceso'] ?? null,
                    'sse_garant' => (bool) ($datos['sse_garant'] ?? false),
                    'sse_pedid'  => !empty($datos['sse_garant']) && !$original ? $datos['sse_pedid'] : null,
                    'sse_dpdid'  => !empty($datos['sse_garant']) && !$original ? $datos['sse_dpdid'] : null,
                    'sse_sseori' => $original?->sse_id,
                    'sse_tenid'  => $tipoEntrega->ten_id,
                    // A domicilio: la sucursal es la que tiene la tarifa (la que retira).
                    'sse_sucid'  => $aDomicilio ? $retiro['suc_id'] : $datos['sse_sucid'],
                    'sse_dirid'  => $aDomicilio ? $datos['sse_dirid'] : null,
                    'sse_fecing' => $datos['sse_fecing'] ?? null,
                    'sse_cosret' => $aDomicilio ? $retiro['costo'] : 0,
                    'sse_etsid'  => EstadoServicio::porCodigo(EstadoServicio::SOLICITADA)->ets_id,
                    'sse_elging' => EstadoLogistica::porCodigo(EstadoLogistica::PENDIENTE, EstadoLogistica::AMBITO_INGRESO)->elg_id,
                    'sse_elgegr' => EstadoLogistica::porCodigo(EstadoLogistica::NO_DEFINIDO, EstadoLogistica::AMBITO_EGRESO)->elg_id,
                    'sse_epaid'  => null,
                ]);

                // Mismo criterio que ped_numero: correlativo real del id.
                $solicitud->update([
                    'sse_numero' => 'SS-' . date('Y') . '-' . str_pad((string) $solicitud->sse_id, 5, '0', STR_PAD_LEFT),
                ]);

                $rutasGuardadas = AdjuntoServicio::guardar($solicitud, $fotos, SolicitudAdjunto::CLIENTE, $usuario->usu_id);

                $this->estados->registrarAlta($solicitud, $usuario->usu_id);

                return $solicitud->fresh();
            });
        } catch (\Throwable $e) {
            // La transacción revierte la BD, pero no el disco.
            Storage::disk('public')->delete($rutasGuardadas);
            throw $e;
        }
    }

    /**
     * Valida que la solicitud original sea del cliente, esté ENTREGADA, con
     * garantía del trabajo vigente y sin otro reclamo abierto.
     */
    public function servicioReclamable(Cliente $cliente, string $numero): SolicitudServicio
    {
        $original = SolicitudServicio::where('sse_cliid', $cliente->cli_id)
            ->where('sse_numero', $numero)
            ->with(['estadoServicio', 'ordenServicio', 'entrega'])
            ->first();

        $vence = $original?->garantiaVenceEl();

        if (!$original || $original->codigoServicio() !== EstadoServicio::ENTREGADA || !$vence || $vence->isPast()) {
            throw ValidationException::withMessages([
                'sse_sseori' => 'Ese servicio no tiene una garantía vigente para reclamar.',
            ]);
        }

        $abierto = $original->reclamos()
            ->whereHas('estadoServicio', fn ($q) => $q->whereNotIn('ets_codigo', [EstadoServicio::ENTREGADA, EstadoServicio::CANCELADA]))
            ->exists();

        if ($abierto) {
            throw ValidationException::withMessages([
                'sse_sseori' => 'Ya tenés un reclamo de garantía en curso para este servicio.',
            ]);
        }

        return $original;
    }

    // ---------- Consulta del cliente ----------

    public function listarDelCliente(Usuario $usuario): Collection
    {
        return SolicitudServicio::where('sse_cliid', $this->clienteDe($usuario)->cli_id)
            ->with(['tipoDispositivo', 'estadoServicio', 'estadoIngreso', 'tipoEntrega'])
            ->orderByDesc('sse_id')
            ->get();
    }

    public function detalleDelCliente(Usuario $usuario, string $numero): SolicitudServicio
    {
        return SolicitudServicio::where('sse_cliid', $this->clienteDe($usuario)->cli_id)
            ->where('sse_numero', $numero)
            ->with([
                'tipoDispositivo', 'estadoServicio', 'estadoIngreso', 'tipoEntrega',
                'sucursal.ciudad', 'direccion.ciudad', 'pedido:ped_id,ped_numero',
                'adjuntos' => fn ($q) => $q->where('sad_tipo', SolicitudAdjunto::CLIENTE),
                'historial' => fn ($q) => $q->where('she_tipo', 'SERVICIO'),
                // Paso 4: presupuesto vigente (el resource solo lo muestra si es visible al cliente)
                'ordenServicio.presupuestoActual.estado',
                'ordenServicio.presupuestoActual.detalles',
                // Paso 6: entrega, garantía del trabajo y reclamos
                'entrega', 'recepcion', 'solicitudOriginal:sse_id,sse_numero',
                'reclamos.estadoServicio',
                // Garantía de compra: producto cubierto y gestión con la empresa responsable
                'detallePedido:dpd_id,dpd_descri', 'garantiaProveedor.proveedor:pro_id,pro_razon',
            ])
            ->firstOrFail();
    }

    // ---------- Auxiliares ----------

    private function clienteDe(Usuario $usuario): Cliente
    {
        $cliente = $usuario->persona?->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        return $cliente;
    }

    private function direccionDelCliente(Usuario $usuario, int $dirId): Direccion
    {
        $direccion = $usuario->persona->direcciones()->activas()->find($dirId);

        if (!$direccion) {
            throw ValidationException::withMessages(['sse_dirid' => 'La dirección seleccionada no es válida.']);
        }

        return $direccion;
    }
}

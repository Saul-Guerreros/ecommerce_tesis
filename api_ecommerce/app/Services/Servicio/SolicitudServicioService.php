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
use App\Models\UsuarioSucursal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FASE 0 - Solicitud del cliente desde la tienda (C2) y su consulta (C3/C4),
 * y el ingreso presencial en el mostrador de la sucursal (segundo canal de entrada).
 */
class SolicitudServicioService
{
    public function __construct(
        private ServicioEstadoService $estados,
        private RecepcionServicioService $recepcion,
        private DiagnosticoPreliminarService $preliminar,
        private ClasificacionSolicitudService $clasificacion,
    ) {}

    // ---------- Configuración del wizard ----------

    public function configuracion(Usuario $usuario): array
    {
        $persona = $usuario->persona;

        return [
            'tipos_dispositivo' => TipoDispositivo::seleccionables()->orderBy('tdi_descri')
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
                $solicitud = $this->crearCabecera([
                    'sse_cliid'  => $cliente->cli_id,
                    // Punto 7: opcional; sin tipo, queda "Por determinar" hasta que la IA lo determine.
                    'sse_tdiid'  => $datos['sse_tdiid'] ?? TipoDispositivo::pendienteId(),
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
                    'sse_canal'  => SolicitudServicio::CANAL_WEB,
                ]);
                $this->preliminar->vincular($datos['dpr_id'] ?? null, $solicitud, $usuario);
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

    // ---------- Alta en el mostrador (canal presencial) ----------

    /**
     * Sucursales donde el funcionario puede registrar: las asignadas y activas en
     * usuario_sucursal; si no tiene ninguna (p. ej. un Administrador), todas las activas.
     */
    public function sucursalesMostrador(Usuario $usuario): Collection
    {
        $asignadas = UsuarioSucursal::where('uss_usuid', $usuario->usu_id)
            ->where('uss_estado', 1)
            ->pluck('uss_sucid');

        return Sucursal::where('suc_estado', 1)
            ->when($asignadas->isNotEmpty(), fn ($q) => $q->whereIn('suc_id', $asignadas))
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre']);
    }

    /** Sucursal principal del funcionario (la que el wizard propone por defecto). */
    public function sucursalPrincipal(Usuario $usuario): ?int
    {
        $id = UsuarioSucursal::where('uss_usuid', $usuario->usu_id)
            ->where('uss_estado', 1)
            ->where('uss_princi', 1)
            ->value('uss_sucid');

        return $id ? (int) $id : null;
    }

    /**
     * Ingreso presencial: crea la solicitud y la RECIBE en una sola transacción.
     *
     * Reutiliza la misma cabecera, historial y recepción (RecepcionServicioService) que el
     * canal web, así que desde RECIBIDA en adelante el flujo es idéntico. Diferencias del canal:
     * modalidad RETIRO, costo de retiro 0, sin agenda (sse_fecing = hoy), sucursal y funcionario.
     *
     * @param array<int, UploadedFile> $fotos fotos de INGRESO (obligatorias)
     */
    public function registrarMostrador(Usuario $usuario, array $datos, array $fotos): SolicitudServicio
    {
        $cliente = Cliente::with('persona')->find($datos['sse_cliid']);

        // El local no atiende los domingos: el ingreso presencial lleva la fecha de hoy (sse_fecing),
        // así que se rechaza aquí. Misma regla que App\Rules\FechaSinDomingo en el canal web.
        if (now()->isSunday()) {
            throw ValidationException::withMessages([
                'sse_fecing' => 'No se pueden registrar ingresos en el mostrador los domingos: el local no atiende ese día.',
            ]);
        }
        if (!$cliente || (int) $cliente->cli_estado !== 1) {
            throw ValidationException::withMessages(['sse_cliid' => 'El cliente no existe o está inactivo.']);
        }
        if (blank($cliente->persona?->per_cedula)) {
            throw ValidationException::withMessages([
                'sse_cliid' => 'Complete el documento del cliente antes de registrar el servicio.',
            ]);
        }

        $sucursal = $this->sucursalesMostrador($usuario)->firstWhere('suc_id', (int) $datos['sse_sucid']);
        if (!$sucursal) {
            throw ValidationException::withMessages(['sse_sucid' => 'No puede registrar servicios en esa sucursal.']);
        }

        // Paso 3 - Reclamo de garantía de un servicio anterior: mismas reglas que la tienda
        // (servicioReclamable). El equipo se toma de la solicitud original, no del formulario.
        $original = null;
        if (!empty($datos['sse_sseori'])) {
            $original = $this->servicioReclamable($cliente, $datos['sse_sseori']);
            $datos = array_merge($datos, [
                'sse_garant' => true,
                'sse_dpdid'  => null,
                'sse_tdiid'  => $original->sse_tdiid,
                'sse_marca'  => $original->sse_marca,
                'sse_modelo' => $original->sse_modelo,
                'sse_nroser' => $original->sse_nroser,
            ]);
        }

        // Garantía de compra: mismas reglas que la tienda (lineasEnGarantia es la única fuente de verdad).
        $garantia = !empty($datos['sse_garant']);
        $linea    = null;
        if ($garantia && !$original) {
            $linea = $this->lineasEnGarantia($cliente)->firstWhere('dpd_id', (int) ($datos['sse_dpdid'] ?? 0));
            if (!$linea) {
                throw ValidationException::withMessages([
                    'sse_dpdid' => 'El producto seleccionado no está dentro del plazo de garantía.',
                ]);
            }
        }

        $recepcion = [
            'rse_tecid'  => (int) $datos['rse_tecid'],
            'rse_estfis' => $datos['rse_estfis'],
            'rse_clave'  => $datos['rse_clave'] ?? null,
            'rse_obs'    => $datos['rse_obs'] ?? null,
            'accesorios' => $datos['accesorios'] ?? [],
        ];

        return DB::transaction(function () use ($usuario, $cliente, $datos, $fotos, $sucursal, $garantia, $linea, $recepcion, $original) {
            $solicitud = $this->crearCabecera([
                'sse_cliid'  => $cliente->cli_id,
                 // Punto 7: opcional; sin tipo, queda "Por determinar" hasta que la IA lo determine.
                'sse_tdiid'  => $datos['sse_tdiid'] ?? TipoDispositivo::pendienteId(),
                'sse_marca'  => $datos['sse_marca'],
                'sse_modelo' => $datos['sse_modelo'],
                'sse_nroser' => $datos['sse_nroser'] ?? null,
                'sse_proble' => $datos['sse_proble'],
                'sse_acceso' => $datos['sse_acceso'] ?? null,
                'sse_garant' => $garantia,
                'sse_pedid'  => $linea['ped_id'] ?? null,
                'sse_dpdid'  => $linea['dpd_id'] ?? null,
                'sse_sseori' => $original?->sse_id,
                'sse_tenid'  => TipoEntrega::porCodigo(TipoEntrega::RETIRO)->ten_id,
                'sse_sucid'  => $sucursal->suc_id,
                'sse_dirid'  => null,
                'sse_fecing' => now()->toDateString(),
                'sse_cosret' => 0,
                'sse_etsid'  => EstadoServicio::porCodigo(EstadoServicio::SOLICITADA)->ets_id,
                'sse_elging' => EstadoLogistica::porCodigo(EstadoLogistica::PENDIENTE, EstadoLogistica::AMBITO_INGRESO)->elg_id,
                'sse_elgegr' => EstadoLogistica::porCodigo(EstadoLogistica::NO_DEFINIDO, EstadoLogistica::AMBITO_EGRESO)->elg_id,
                'sse_epaid'  => null,
                'sse_canal'  => SolicitudServicio::CANAL_MOSTRADOR,
                'sse_usuid'  => $usuario->usu_id,
            ]);
            $this->preliminar->vincular($datos['dpr_id'] ?? null, $solicitud, $usuario);
            $this->estados->registrarAlta($solicitud, $usuario->usu_id, $original
                ? "Reclamo de garantía de {$original->sse_numero} registrado en el mostrador de {$sucursal->suc_nombre}"
                : "Solicitud registrada en el mostrador de {$sucursal->suc_nombre}");

            // Misma recepción que A2 (técnico de la sucursal, accesorios, fotos de INGRESO, clave cifrada):
            // SOLICITADA/PENDIENTE -> RECIBIDA/RECIBIDO, con su historial y el aviso al cliente.
            // Su transacción interna corre como savepoint de esta; el correo sale después del commit.
            return $this->recepcion->confirmarRecepcion($solicitud->fresh(), $recepcion, $fotos, $usuario);
        });
    }

    /**
     * Paso 3 - Servicios del cliente que admiten un reclamo de garantía: ENTREGADOS, con la
     * garantía del trabajo vigente y sin otro reclamo abierto (mismo criterio que servicioReclamable).
     */
    public function reclamablesDe(Cliente $cliente): Collection
    {
        return SolicitudServicio::where('sse_cliid', $cliente->cli_id)
            ->whereHas('estadoServicio', fn ($q) => $q->where('ets_codigo', EstadoServicio::ENTREGADA))
            ->with(['tipoDispositivo', 'estadoServicio', 'ordenServicio', 'entrega', 'reclamos.estadoServicio'])
            ->orderByDesc('sse_id')
            ->get()
            ->filter(fn (SolicitudServicio $s) => $this->esReclamable($s))
            ->map(fn (SolicitudServicio $s) => $this->datosReclamable($s))
            ->values();
    }

    /** Datos para precargar el wizard con ?reclamo=SS-... (cliente y equipo bloqueados). */
    public function reclamoPorNumero(string $numero): SolicitudServicio
    {
        $s = SolicitudServicio::with('cliente.persona')->where('sse_numero', $numero)->first();
        if (!$s) {
            throw ValidationException::withMessages(['sse_sseori' => 'No existe una solicitud con ese número.']);
        }

        return $this->servicioReclamable($s->cliente, $numero);
    }

    public function esReclamable(SolicitudServicio $s): bool
    {
        $vence = $s->garantiaVenceEl();

        return $s->codigoServicio() === EstadoServicio::ENTREGADA
            && $vence && !$vence->isPast()
            && !$s->reclamos->contains(fn ($r) => !in_array($r->estadoServicio?->ets_codigo,
                [EstadoServicio::ENTREGADA, EstadoServicio::CANCELADA], true));
    }

    public function datosReclamable(SolicitudServicio $s): array
    {
        return [
            'sse_numero' => $s->sse_numero,
            'equipo'     => trim(($s->tipoDispositivo?->tdi_descri ?? '') . ' ' . $s->sse_marca . ' ' . $s->sse_modelo),
            'sse_tdiid'  => $s->sse_tdiid,
            'sse_marca'  => $s->sse_marca,
            'sse_modelo' => $s->sse_modelo,
            'sse_nroser' => $s->sse_nroser,
            'entregado'  => $s->entrega?->ese_fecha?->format('Y-m-d'),
            'vence'      => $s->garantiaVenceEl()?->format('Y-m-d'),
        ];
    }

    /** Cabecera + número correlativo, compartido por ambos canales. */
    private function crearCabecera(array $atributos): SolicitudServicio
    {
        $solicitud = SolicitudServicio::create($atributos);

        // Mismo criterio que ped_numero: correlativo real del id (global, no por sucursal).
        $solicitud->update([
            'sse_numero' => 'SS-' . date('Y') . '-' . str_pad((string) $solicitud->sse_id, 5, '0', STR_PAD_LEFT),
        ]);

        // Punto 7: punto único de alta (web, mostrador, garantía y reclamo) => toda solicitud se clasifica.
        $tdiCliente = (int) $solicitud->sse_tdiid === TipoDispositivo::pendienteId() ? null : (int) $solicitud->sse_tdiid;
        $this->clasificacion->registrarPendiente($solicitud, $tdiCliente);
        
        return $solicitud;
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
            ->with(['tipoDispositivo', 'estadoServicio', 'estadoIngreso', 'tipoEntrega', 'estadoPago'])
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
                // Pago del servicio (C4)
                'estadoPago', 'pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'pagos.motivoRechazo',
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

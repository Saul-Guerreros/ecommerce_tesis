<?php

namespace App\Services\Servicio;

use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\TipoEntrega;
use App\Models\Servicio\EntregaServicio;
use App\Models\Servicio\EstadoLogistica;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\RecepcionAccesorio;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FASE 7 - Entrega y cierre (A5).
 *
 * Regla 7: si hay monto a cobrar, no se entrega hasta que el pago esté
 * CONFIRMADO. sse_epaid lo deriva EstadoPagoServicioService de servicio_pago
 * (pago web verificado o cobro en mostrador). "Cobrar y entregar" vive en
 * CobroMostradorService y termina llamando a registrar(): la regla se
 * aplica igual por los dos caminos.
 */
class EntregaServicioService
{
    public function __construct(private ServicioEstadoService $estados) {}

    /** Datos para precargar el modal A5. */
    public function configuracion(SolicitudServicio $s): array
    {
        $persona = $s->cliente?->persona;

        // Paso 3 (B.4-a / B.5-a): el canal mostrador se entrega solo en su sucursal (cada sucursal
        // tiene su taller, sin traslados) y sin envío: el envío lo decidirá el cierre de RF-038 (C5).
        $mostrador = $s->esMostrador();

        return [
            'solo_retiro_sucursal' => $mostrador,
            'titular' => [
                'nrodoc' => $persona?->per_cedula,
                'nombre' => $persona?->per_nombr1,
                'apelli' => $persona?->per_apell1,
                'telef'  => $persona?->contactos?->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])->first()?->con_valor,
            ],
            'sucursales'  => $mostrador
                ? Sucursal::whereKey($s->sse_sucid)->get(['suc_id', 'suc_nombre'])
                : Sucursal::paraRetiro()->orderByDesc('suc_princi')->get(['suc_id', 'suc_nombre']),
            'direcciones' => $mostrador ? [] : ($persona ? $persona->direcciones()->activas()->with('ciudad')->get()
                ->map(fn ($d) => ['dir_id' => $d->dir_id, 'resumen' => $d->resumen]) : []),
            'accesorios'  => $s->recepcion?->accesorios->map(fn ($a) => ['rac_id' => $a->rac_id, 'rac_descri' => $a->rac_descri]) ?? [],
            'cobro'       => $this->estadoCobro($s),
        ];
    }

        /**
     * Regla 7 con facturación: si hay monto a cobrar, se entrega con la
     * factura final emitida y el cobro CONFIRMADO. Además, ningún saldo de
     * anticipo puede quedar sin aplicar ni devolver.
     *
     * @return array{habilitado: bool, motivo: ?string, total: float}
     */
    public function estadoCobro(SolicitudServicio $s): array
    {
        $total = (float) $s->sse_total;
        $cobra = $total > 0 && !$s->sse_garant;

        if ($cobra && !$s->factura()->exists()) {
            return ['habilitado' => false, 'motivo' => 'Falta generar la factura del servicio.', 'total' => $total];
        }

        $anticipo = $s->anticipoSinResolver();

        if ($anticipo > 0.01) {
            return [
                'habilitado' => false,
                'motivo'     => 'Hay Gs. ' . number_format($anticipo, 0, ',', '.')
                     . ' de anticipo sin resolver: acreditalo con nota de crédito y registrá la devolución antes de entregar.',
                'total'      => $total,
            ];
        }

        $porDevolverFinal = $s->factura()->first()?->dineroPorDevolver() ?? 0.0;

        if ($porDevolverFinal > 0.01) {
            return [
                'habilitado' => false,
                'motivo'     => 'Hay Gs. ' . number_format($porDevolverFinal, 0, ',', '.')
                              . ' cobrados de más en la factura del servicio: registrá la devolución antes de entregar.',
                'total'      => $total,
            ];
        }

        if (!$cobra) {
            return ['habilitado' => true, 'motivo' => null, 'total' => 0.0];
        }

        $confirmado = $s->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO;

        return [
            'habilitado' => $confirmado,
            'motivo'     => $confirmado ? null : 'El cobro está pendiente: el equipo se entrega cuando el pago esté confirmado.',
            'total'      => $total,
        ];
    }

       public function registrar(SolicitudServicio $s, array $d, Usuario $usuario): EntregaServicio
    {
        if ($s->codigoServicio() !== EstadoServicio::LISTA || $s->entrega()->exists()) {
            throw ValidationException::withMessages(['modalidad' => 'Solo se entrega un equipo LISTO y sin entrega registrada.']);
        }

        $cobro = $this->estadoCobro($s);
        if (!$cobro['habilitado']) {
            throw ValidationException::withMessages(['modalidad' => $cobro['motivo']]);
        }

        return DB::transaction(function () use ($s, $d, $usuario) {
            $entrega = $this->crearRegistro($s, $d, $usuario, EntregaServicio::TIPO_ENTREGA);

            $gar = $s->ordenServicio?->ose_gardia;
            $this->estados->cambiarServicio($s, EstadoServicio::ENTREGADA, $usuario->usu_id,
                'Equipo entregado' . ($gar ? ". Garantía del trabajo: {$gar} días desde hoy" : ''));

            return $entrega;
        });
    }

    /**
     * Devolución del equipo por CANCELACIÓN: misma trazabilidad que la
     * entrega, sin cobro y sin cambiar el estado del servicio (lo hace
     * CancelacionServicioService en la misma transacción).
     */
    public function registrarDevolucion(SolicitudServicio $s, array $d, Usuario $usuario): EntregaServicio
    {
        if ($s->entrega()->exists()) {
            throw ValidationException::withMessages(['devolucion' => 'El equipo ya tiene una salida registrada.']);
        }

        return $this->crearRegistro($s, $d, $usuario, EntregaServicio::TIPO_DEVOLUCION);
    }

    /**
     * Salida física del equipo (entrega o devolución): receptor, sucursal o
     * dirección, accesorios devueltos y logística de egreso.
     */
    private function crearRegistro(SolicitudServicio $s, array $d, Usuario $usuario, string $tipo): EntregaServicio
    {
        if ($s->esMostrador()) {
            if (($d['modalidad'] ?? null) !== 'retiro') {
                throw ValidationException::withMessages([
                    'modalidad' => 'Los servicios de mostrador se entregan en la sucursal donde se recibieron.',
                ]);
            }
            if ((int) ($d['ese_sucid'] ?? 0) !== (int) $s->sse_sucid) {
                throw ValidationException::withMessages([
                    'ese_sucid' => 'El equipo se entrega en la sucursal donde fue recibido.',
                ]);
            }
        }

        // Accesorios: lo que entró tiene que salir; si no, se explica por qué.
        $recibidos = $s->recepcion?->accesorios ?? collect();
        $devueltos = collect($d['accesorios'] ?? [])->map(fn ($id) => (int) $id);
        $faltantes = $recibidos->reject(fn ($a) => $devueltos->contains($a->rac_id));

        if ($faltantes->isNotEmpty() && blank($d['ese_obs'] ?? null)) {
            throw ValidationException::withMessages([
                'ese_obs' => 'No se marcaron como devueltos: ' . $faltantes->pluck('rac_descri')->implode(', ')
                    . '. Indique el motivo en observaciones.',
            ]);
        }

        $envio = $d['modalidad'] === 'envio';

        return DB::transaction(function () use ($s, $d, $usuario, $tipo, $envio, $recibidos, $devueltos) {
            $receptor = $d['receptor'] === 'titular' ? $this->titular($s, $d) : [
                'ese_nrodoc' => $d['ese_nrodoc'], 'ese_nombre' => $d['ese_nombre'],
                'ese_apelli' => $d['ese_apelli'], 'ese_telef' => $d['ese_telef'] ?? null,
            ];

            $entrega = EntregaServicio::create(array_merge(
                $receptor,
                $envio ? $this->snapshotDireccion($s, (int) $d['ese_dirid']) : ['ese_sucid' => (int) $d['ese_sucid']],
                [
                    'ese_sseid'  => $s->sse_id,
                    'ese_tipo'   => $tipo,
                    'ese_tenid'  => TipoEntrega::porCodigo($envio ? TipoEntrega::ENVIO : TipoEntrega::RETIRO)->ten_id,
                    'ese_titula' => $d['receptor'] === 'titular',
                    'ese_cosenv' => 0,
                    'ese_obs'    => $d['ese_obs'] ?? null,
                    'ese_fecha'  => now(),
                    'ese_usuid'  => $usuario->usu_id,
                ],
            ));

            RecepcionAccesorio::whereIn('rac_id', $recibidos->pluck('rac_id'))
                ->get()
                ->each(fn ($a) => $a->update(['rac_devuel' => $devueltos->contains($a->rac_id)]));

            $accion = $tipo === EntregaServicio::TIPO_DEVOLUCION
                ? 'Devuelto sin reparar (cancelación)'
                : ($envio ? 'Entregado en domicilio' : 'Retirado en sucursal');

            $this->estados->cambiarEgreso($s, EstadoLogistica::ENTREGADO, $usuario->usu_id,
                "{$accion} a {$entrega->ese_nombre} {$entrega->ese_apelli} (C.I. {$entrega->ese_nrodoc})");

            return $entrega;
        });
    }

    private function titular(SolicitudServicio $s, array $d): array
    {
        $p = $s->cliente->persona;
        $nrodoc = $p->per_cedula ?: ($d['ese_nrodoc'] ?? null);

        if (blank($nrodoc)) {
            throw ValidationException::withMessages(['ese_nrodoc' => 'El cliente no tiene C.I. registrada: ingrésela.']);
        }

        return [
            'ese_nrodoc' => $nrodoc,
            'ese_nombre' => $p->per_nombr1,
            'ese_apelli' => $p->per_apell1,
            'ese_telef'  => $d['ese_telef'] ?? $p->contactos->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])->first()?->con_valor,
        ];
    }

    /** Congela la dirección como texto (patrón pedido_entrega). */
    private function snapshotDireccion(SolicitudServicio $s, int $dirId): array
    {
        $dir = $s->cliente->persona->direcciones()->activas()->with('ciudad.departamento')->find($dirId);

        if (!$dir) {
            throw ValidationException::withMessages(['ese_dirid' => 'La dirección no pertenece al cliente.']);
        }

        return [
            'ese_dirid'      => $dir->dir_id,
            'ese_departa'    => $dir->ciudad?->departamento?->dep_descri,
            'ese_ciudad'     => $dir->ciudad?->ciu_descri,
            'ese_barrio'     => $dir->dir_barrio,
            'ese_calle'      => $dir->dir_calle,
            'ese_nrocasa'    => $dir->dir_nrocasa,
            'ese_referencia' => $dir->dir_referencia,
        ];
    }
}

<?php

namespace App\Services\Servicio;

use App\Models\Producto;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\OrdenServicio;
use App\Models\Servicio\PresupuestoDetalle;
use App\Models\Servicio\PresupuestoServicio;
use App\Models\Servicio\ServicioHistorialEstado;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\Trabajo;
use App\Models\Usuario;
use App\Services\Servicio\ServicioTotalesService as Totales;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FASES 3 y 4 - Presupuesto (T3), validación del admin y decisión del cliente (C4).
 *
 *   BORRADOR --técnico--> EN_REVISION --admin--> ENVIADO --cliente--> APROBADO
 *       ^                     |                     |--cliente--> RECHAZADO
 *       +------devuelve-------+                     +--job------> VENCIDO
 *
 * Garantía (regla 8): precios en 0 y BORRADOR -> APROBADO directo.
 *
 * Paso 5 - Presupuesto COMPLEMENTARIO (pse_versio + 1): si en la reparación
 * aparece un repuesto o trabajo no presupuestado, NUNCA se edita el aprobado
 * (regla 5): se crea una versión nueva con SOLO lo adicional, que recorre el
 * mismo circuito. Si el cliente la rechaza o vence, la reparación sigue con
 * lo ya aprobado (no se cierra la OT).
 */
class PresupuestoService
{
    public function __construct(
        private ServicioEstadoService $estados,
        private ServicioTotalesService $totales,
        private StockServicioService $stock,
    ) {}

    // =====================================================================
    // Técnico (T3)
    // =====================================================================

    /** Crea la v1 o reemplaza las líneas del borrador vigente. */
    public function guardarBorrador(OrdenServicio $orden, array $d, Usuario $usuario): PresupuestoServicio
    {
        $s = $orden->solicitud;
        $this->exigirPresupuestable($s, $orden);

        $actual = $orden->presupuestoActual;
        if ($actual && !$actual->esEditable()) {
            throw ValidationException::withMessages([
                'lineas' => 'El presupuesto ya fue enviado a validación o al cliente y no puede editarse.',
            ]);
        }

        $deposito = $this->stock->depositoTaller($s);

        return DB::transaction(function () use ($orden, $s, $d, $usuario, $actual, $deposito) {
            $p = $actual ?? PresupuestoServicio::create([
                'pse_oseid'  => $orden->ose_id,
                'pse_versio' => 1,
                'pse_eprid'  => EstadoPresupuesto::porCodigo(EstadoPresupuesto::BORRADOR)->epr_id,
                'pse_usuid'  => $usuario->usu_id,
            ]);

            if (!$p->pse_numero) {
                $p->pse_numero = 'PS-' . date('Y') . '-' . str_pad((string) $p->pse_id, 5, '0', STR_PAD_LEFT);
            }

            $p->detalles()->delete();

            $subtot = $impues = 0.0;
            foreach ($d['lineas'] as $l) {
                $linea = $this->armarLinea($l, (bool) $s->sse_garant, $deposito?->dpo_id);
                $p->detalles()->create($linea);
                $subtot += $linea['psd_total'];
                $impues += $linea['psd_iva'];
            }

            $p->fill([
                'pse_subtot' => $subtot,
                'pse_impues' => $impues,
                'pse_total'  => $subtot,
                'pse_obs'    => $d['pse_obs'] ?? null,
                'pse_usuid'  => $usuario->usu_id,
            ])->save();

            return $p->fresh(['detalles', 'estado']);
        });
    }

    /** Abre la versión complementaria (vacía) durante la reparación. */
    public function crearComplementario(OrdenServicio $orden, Usuario $usuario): PresupuestoServicio
    {
        $s      = $orden->solicitud;
        $actual = $orden->presupuestoActual;

        if ($s->codigoServicio() !== EstadoServicio::EN_REPARACION || $actual?->codigoEstado() !== EstadoPresupuesto::APROBADO) {
            throw ValidationException::withMessages([
                'lineas' => 'El complementario se abre durante la reparación, sobre un presupuesto aprobado.',
            ]);
        }

        return DB::transaction(function () use ($orden, $s, $actual, $usuario) {
            $p = PresupuestoServicio::create([
                'pse_oseid'  => $orden->ose_id,
                'pse_versio' => $actual->pse_versio + 1,
                'pse_eprid'  => EstadoPresupuesto::porCodigo(EstadoPresupuesto::BORRADOR)->epr_id,
                'pse_usuid'  => $usuario->usu_id,
            ]);
            $p->update(['pse_numero' => 'PS-' . date('Y') . '-' . str_pad((string) $p->pse_id, 5, '0', STR_PAD_LEFT)]);

            $orden->update(['ose_ajuste' => true]);

            $this->estados->registrarNota($s, $usuario->usu_id,
                "Presupuesto complementario {$p->pse_numero} (v{$p->pse_versio}) abierto durante la reparación");

            return $p->fresh(['detalles', 'estado']);
        });
    }

    /** El técnico desiste del complementario mientras sigue en BORRADOR. */
    public function descartarComplementario(OrdenServicio $orden, Usuario $usuario): void
    {
        $p = $orden->presupuestoActual;

        if (!$p || $p->pse_versio < 2 || !$p->esEditable()) {
            throw ValidationException::withMessages(['lineas' => 'Solo se descarta un complementario en borrador.']);
        }

        DB::transaction(function () use ($orden, $p, $usuario) {
            $numero = $p->pse_numero;
            $p->delete();   // las líneas caen por ON DELETE CASCADE

            // ose_ajuste refleja si quedó ALGÚN complementario.
            $orden->update(['ose_ajuste' => $orden->presupuestos()->where('pse_versio', '>', 1)->exists()]);
            $this->estados->registrarNota($orden->solicitud, $usuario->usu_id, "Complementario {$numero} descartado");
        });
    }

    /** El técnico lo envía al admin. En garantía se aprueba solo (sin cobro ni validación). */
    public function enviarAValidacion(OrdenServicio $orden, Usuario $usuario): PresupuestoServicio
    {
        $s = $orden->solicitud;
        $this->exigirPresupuestable($s, $orden);

        $p = $orden->presupuestoActual;
        if (!$p || !$p->esEditable() || $p->detalles()->count() === 0) {
            throw ValidationException::withMessages(['lineas' => 'Guarde un presupuesto con al menos una línea antes de enviarlo.']);
        }

        return DB::transaction(function () use ($s, $p, $usuario) {
            if ($s->sse_garant) {
                $this->cambiarEstado($s, $p, EstadoPresupuesto::APROBADO, $usuario->usu_id, 'Garantía: presupuesto aprobado sin cargo');
                $p->update(['pse_respon' => now(), 'pse_enviad' => now()]);
                $this->reservarYAprobar($s, $p, $usuario->usu_id);
            } else {
                $p->update(['pse_obsval' => null]);
                $this->cambiarEstado($s, $p, EstadoPresupuesto::EN_REVISION, $usuario->usu_id, 'Enviado a validación de precios');
            }

            return $p->fresh(['detalles', 'estado']);
        });
    }

    // =====================================================================
    // Administrador (control de precios)
    // =====================================================================

    public function validarYEnviar(PresupuestoServicio $p, Usuario $admin): PresupuestoServicio
    {
        $this->exigirEstado($p, EstadoPresupuesto::EN_REVISION);
        $s = $p->orden->solicitud;

        return DB::transaction(function () use ($s, $p, $admin) {
            $p->update([
                'pse_valusu' => $admin->usu_id,
                'pse_enviad' => now(),
                // El plazo corre desde que el cliente puede verlo.
                'pse_fecven' => now()->addDays(PresupuestoServicio::DIAS_VIGENCIA)->toDateString(),
            ]);

            $this->cambiarEstado($s, $p, EstadoPresupuesto::ENVIADO, $admin->usu_id,
                'Enviado al cliente. Vence el ' . $p->pse_fecven->format('d/m/Y'));

            $this->estados->cambiarServicio($s, EstadoServicio::PRESUPUESTADA, $admin->usu_id,
                "Presupuesto {$p->pse_numero} enviado: Gs. " . number_format((float) $p->pse_total, 0, ',', '.'));


            return $p->fresh(['detalles', 'estado']);
        });
    }

    public function devolverAlTecnico(PresupuestoServicio $p, string $obs, Usuario $admin): PresupuestoServicio
    {
        $this->exigirEstado($p, EstadoPresupuesto::EN_REVISION);
        $s = $p->orden->solicitud;

        return DB::transaction(function () use ($s, $p, $obs, $admin) {
            $p->update(['pse_obsval' => $obs]);
            $this->cambiarEstado($s, $p, EstadoPresupuesto::BORRADOR, $admin->usu_id, "Devuelto al técnico: {$obs}");

            return $p->fresh(['detalles', 'estado']);
        });
    }

    // =====================================================================
    // Cliente (C4) y job de vencimiento
    // =====================================================================

    public function aprobarPorCliente(SolicitudServicio $s, Usuario $cliente): PresupuestoServicio
    {
        $p = $this->presupuestoEnviado($s);

        return DB::transaction(function () use ($s, $p, $cliente) {
            $p->update(['pse_respon' => now()]);
            $this->cambiarEstado($s, $p, EstadoPresupuesto::APROBADO, $cliente->usu_id, 'Aprobado por el cliente');
            $this->reservarYAprobar($s, $p, $cliente->usu_id);

            return $p->fresh(['detalles', 'estado']);
        });
    }

    public function rechazarPorCliente(SolicitudServicio $s, string $motivo, Usuario $cliente): PresupuestoServicio
    {
        $p = $this->presupuestoEnviado($s);

        return DB::transaction(function () use ($s, $p, $motivo, $cliente) {
            $p->update(['pse_respon' => now(), 'pse_motrec' => $motivo]);
            $this->cambiarEstado($s, $p, EstadoPresupuesto::RECHAZADO, $cliente->usu_id, "Rechazado por el cliente: {$motivo}");

            $p->pse_versio > 1
                ? $this->volverAlAprobado($s, $cliente->usu_id, 'Complementario rechazado: se continúa con lo ya aprobado')
                : $this->cerrarSinReparar($s, $p, "Presupuesto rechazado por el cliente: {$motivo}", $cliente->usu_id);

            return $p->fresh(['detalles', 'estado']);
        });
    }

    /** Lo usa el comando programado: sin respuesta en plazo => mismo camino que el rechazo. */
    public function vencer(PresupuestoServicio $p): void
    {
        if ($p->codigoEstado() !== EstadoPresupuesto::ENVIADO || !$p->pse_fecven?->lt(today())) {
            return;
        }

        $s = $p->orden->solicitud;

        DB::transaction(function () use ($s, $p) {
            $p->update(['pse_motrec' => 'Vencido sin respuesta del cliente']);
            $this->cambiarEstado($s, $p, EstadoPresupuesto::VENCIDO, null, 'Vencido sin respuesta del cliente');

            $p->pse_versio > 1
                ? $this->volverAlAprobado($s, null, 'Complementario vencido: se continúa con lo ya aprobado')
                : $this->cerrarSinReparar($s, $p, 'Presupuesto vencido sin respuesta del cliente', null);
        });
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    /** Arma una línea con precio e IVA resueltos en el servidor (nunca confía en el total enviado). */
    private function armarLinea(array $l, bool $garantia, ?int $depositoId): array
    {
        $cantidad = (float) $l['cantid'];

        if ($l['tipo'] === PresupuestoDetalle::MANO_OBRA) {
            $trabajo = Trabajo::activos()->with('tipoIva')->find($l['trb_id']);
            if (!$trabajo) {
                throw ValidationException::withMessages(['lineas' => 'Uno de los trabajos no existe o está inactivo.']);
            }
            $precio = $l['precio'] ?? (float) $trabajo->trb_precio;
            $tasa   = (float) ($trabajo->tipoIva->tiv_tasa ?? 0);
            $base   = ['psd_trbid' => $trabajo->trb_id, 'psd_prdid' => null, 'psd_depid' => null,
                       'psd_descri' => ($l['descri'] ?? null) ?: $trabajo->trb_descri];
        } else {
            $producto = Producto::with('tipoIva')->where('prd_estado', 1)->where('prd_repues', 1)->find($l['prd_id']);
            if (!$producto) {
                throw ValidationException::withMessages(['lineas' => 'Uno de los repuestos no existe, está inactivo o no está marcado como repuesto de taller.']);
            }
            if (!$depositoId) {
                throw ValidationException::withMessages(['lineas' => 'No hay un depósito principal configurado para el taller.']);
            }
            $precio = $l['precio'] ?? $producto->precioPara($cantidad);
            if ($precio === null) {
                throw ValidationException::withMessages(['lineas' => "«{$producto->prd_codinv}» no tiene precio de venta: indíquelo manualmente."]);
            }
            $tasa = (float) ($producto->tipoIva->tiv_tasa ?? 0);
            $base = ['psd_prdid' => $producto->prd_id, 'psd_trbid' => null, 'psd_depid' => $depositoId,
                     'psd_descri' => ($l['descri'] ?? null) ?: mb_substr((string) $producto->prd_descri, 0, 150)];
        }

        $precio   = $garantia ? 0.0 : round((float) $precio, 2);
        $subtotal = round($cantidad * $precio, 2);

        return $base + [
            'psd_tipo'   => $l['tipo'],
            'psd_cantid' => $cantidad,
            'psd_precio' => $precio,
            'psd_subtot' => $subtotal,
            'psd_iva'    => Totales::ivaIncluido($subtotal, $tasa),
            'psd_total'  => $subtotal,
            'psd_reserv' => false,
        ];
    }

    private function reservarYAprobar(SolicitudServicio $s, PresupuestoServicio $p, ?int $usuId): void
    {
        $faltantes = $this->stock->reservar($p);

        // Complementario de garantía: se aprueba sin salir de EN_REPARACION.
        if ($s->codigoServicio() === EstadoServicio::EN_REPARACION) {
            $this->estados->registrarNota($s, $usuId, "Presupuesto {$p->pse_numero} aprobado (garantía)");
        } else {
            $this->estados->cambiarServicio($s, EstadoServicio::APROBADA, $usuId, "Presupuesto {$p->pse_numero} aprobado");
        }

        if ($faltantes) {
            $this->estados->registrarNota($s, $usuId, 'Repuestos sin stock (pendientes de compra): ' . implode(', ', $faltantes));
        }
    }

    /** Complementario rechazado/vencido: la solicitud vuelve a APROBADA y el técnico reanuda. */
    private function volverAlAprobado(SolicitudServicio $s, ?int $usuId, string $coment): void
    {
        $this->estados->cambiarServicio($s, EstadoServicio::APROBADA, $usuId, $coment);
    }

    /** Rechazo o vencimiento: la OT se cierra sin reparar y el equipo queda LISTO para devolver. */
    private function cerrarSinReparar(SolicitudServicio $s, PresupuestoServicio $p, string $proced, ?int $usuId): void
    {
        $orden = $p->orden;
        $orden->update([
            'ose_result' => OrdenServicio::NO_REPARABLE,
            'ose_fecfin' => now(),
            'ose_proced' => $proced,
            'ose_gardia' => 0,
        ]);

        // Sin trabajo realizado: solo se cobra el retiro a domicilio, si hubo.
        $this->totales->consolidar($s, 0.0, 0.0, $usuId);

        $this->estados->cambiarServicio($s, EstadoServicio::LISTA, $usuId, 'Equipo listo para devolver sin reparar');
    }

    private function cambiarEstado(SolicitudServicio $s, PresupuestoServicio $p, string $destino, ?int $usuId, string $coment): void
    {
        $nuevo    = EstadoPresupuesto::porCodigo($destino);
        $anterior = $p->pse_eprid;

        $p->update(['pse_eprid' => $nuevo->epr_id]);
        $p->setRelation('estado', $nuevo);

        ServicioHistorialEstado::create([
            'she_sseid'  => $s->sse_id,
            'she_usuid'  => $usuId,
            'she_tipo'   => ServicioHistorialEstado::PRESUPUESTO,
            'she_estant' => $anterior,
            'she_estid'  => $nuevo->epr_id,
            'she_coment' => "{$p->pse_numero} v{$p->pse_versio}: {$coment}",
            'she_ip'     => request()->ip(),
        ]);
    }

    private function exigirPresupuestable(SolicitudServicio $s, OrdenServicio $orden): void
    {
        $dia    = $orden->diagnostico;
        $actual = $orden->presupuestoActual;

        $original = $s->codigoServicio() === EstadoServicio::EN_DIAGNOSTICO && $dia?->dia_repara;
        $complementario = $s->codigoServicio() === EstadoServicio::EN_REPARACION
            && $actual && $actual->pse_versio > 1 && $actual->esEditable();

        if ((!$original && !$complementario) || $orden->estaCerrada()) {
            throw ValidationException::withMessages([
                'lineas' => 'Solo se presupuesta una orden en diagnóstico (equipo reparable) o un complementario abierto en reparación.',
            ]);
        }
    }

    private function exigirEstado(PresupuestoServicio $p, string $codigo): void
    {
        if ($p->codigoEstado() !== $codigo) {
            throw ValidationException::withMessages(['estado' => "El presupuesto no está en estado {$codigo}."]);
        }
    }

    private function presupuestoEnviado(SolicitudServicio $s): PresupuestoServicio
    {
        $p = $s->ordenServicio?->presupuestoActual;

        if (!$p || $p->codigoEstado() !== EstadoPresupuesto::ENVIADO) {
            throw ValidationException::withMessages(['presupuesto' => 'No hay un presupuesto pendiente de respuesta.']);
        }
        if ($p->pse_fecven && $p->pse_fecven->lt(today())) {
            throw ValidationException::withMessages(['presupuesto' => 'El presupuesto venció. Comunicate con la tienda.']);
        }

        return $p;
    }
}

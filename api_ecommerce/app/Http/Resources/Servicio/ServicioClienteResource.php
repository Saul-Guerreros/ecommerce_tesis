<?php

namespace App\Http\Resources\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\GarantiaProveedor;
use App\Services\Servicio\EstadoPagoServicioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vista del CLIENTE (C3/C4): nunca expone los códigos internos de los ejes,
 * solo ets_visible. La "acción pendiente" orienta qué tiene que hacer.
 */
class ServicioClienteResource extends JsonResource
{
    /** Pasos del timeline de C4 (SOLICITADO y CANCELADO quedan fuera). */
    public const PASOS = ['RECIBIDO', 'EN DIAGNÓSTICO', 'EN REPARACIÓN', 'LISTO', 'ENTREGADO'];

    /** Garantía de compra: no hay diagnóstico ni reparación en el taller, el reclamo va a la empresa. */
    public const PASOS_GARANTIA = ['RECIBIDO', 'EN GESTIÓN', 'EN LA EMPRESA', 'LISTO', 'ENTREGADO'];

    public function toArray(Request $request): array
    {
        $visible = $this->estadoServicio?->ets_visible;
        $pasos   = $this->esGarantiaDeCompra() ? self::PASOS_GARANTIA : self::PASOS;
        $paso    = array_search($visible, $pasos, true);

        return [
            'sse_numero'  => $this->sse_numero,
            'sse_creado'  => $this->sse_creado?->format('Y-m-d H:i'),
            'equipo'      => trim(($this->tipoDispositivo?->tdi_descri ?? '') . ' ' . $this->sse_marca . ' ' . $this->sse_modelo),
            'sse_nroser'  => $this->sse_nroser,
            'sse_garant'  => $this->sse_garant,
            'estado'      => $visible,
            'pasos'       => $pasos,
            'paso_actual' => $paso === false ? null : $paso + 1,
            'cancelado'   => $this->estadoServicio?->ets_codigo === 'CANCELADA',
            'accion_pendiente' => $this->accionPendiente(),

            // Solo en el detalle (C4)
            'sse_proble' => $this->when($this->relationLoaded('adjuntos'), $this->sse_proble),
            'sse_acceso' => $this->when($this->relationLoaded('adjuntos'), $this->sse_acceso),
            'pedido'     => $this->whenLoaded('pedido', fn () => $this->pedido?->ped_numero),
            'ingreso'    => $this->when($this->relationLoaded('adjuntos'), fn () => [
                'a_domicilio' => $this->esADomicilio(),
                'sucursal'    => $this->sucursal?->suc_nombre,
                'direccion'   => $this->direccion?->resumen,
                'fecha'       => $this->sse_fecing?->format('Y-m-d'),
                'costo'       => (float) $this->sse_cosret,
                'estado'      => $this->estadoIngreso?->elg_descri,
            ]),
            'fotos'     => $this->whenLoaded('adjuntos', fn () => $this->adjuntos->map(fn ($a) => $a->url)),
            'presupuesto' => $this->when($this->relationLoaded('ordenServicio'), fn () => $this->presupuestoVisible()),

            // ---- Paso 6 (solo en el detalle) ----
            'equipo_datos' => $this->when($this->relationLoaded('adjuntos'), fn () => [
                'tdi_id' => $this->sse_tdiid, 'marca' => $this->sse_marca,
                'modelo' => $this->sse_modelo, 'nroser' => $this->sse_nroser,
            ]),
            'reclamo_de'  => $this->whenLoaded('solicitudOriginal', fn () => $this->solicitudOriginal?->sse_numero),
            'producto'    => $this->whenLoaded('detallePedido', fn () => $this->detallePedido?->dpd_descri),
            'garantia_empresa' => $this->when(
                $this->relationLoaded('garantiaProveedor') && $this->esGarantiaDeCompra(),
                fn () => $this->garantiaEmpresaVisible()
            ),
            'resultado'   => $this->when($this->relationLoaded('ordenServicio'), fn () => $this->resultadoVisible()),
            'entrega'     => $this->whenLoaded('entrega', fn () => $this->entrega ? [
                'fecha'    => $this->entrega->ese_fecha?->format('Y-m-d H:i'),
                'receptor' => trim($this->entrega->ese_nombre . ' ' . $this->entrega->ese_apelli),
            ] : null),
            'garantia'    => $this->when($this->relationLoaded('entrega'), fn () => $this->garantiaVisible()),
            'constancias' => $this->when($this->relationLoaded('recepcion'), fn () => [
                'recepcion' => $this->recepcion !== null,
                'retiro'    => $this->entrega !== null,
            ]),
            // ---- Pago del servicio (solo en el detalle) ----
            'pago' => $this->when($this->relationLoaded('pagos'), fn () => $this->pagoVisible()),

            'historial' => $this->whenLoaded('historial', fn () => $this->historial->map(fn ($h) => [
                'fecha'  => $h->she_fecha?->format('Y-m-d H:i'),
                'coment' => $h->she_coment,
            ])),
        ];
    }

    /**
     * El cliente solo ve el presupuesto desde que se le envía (los borradores
     * y la validación interna no son visibles). Sin depósito ni stock.
     */
    private function presupuestoVisible(): ?array
    {
        $p = $this->ordenServicio?->presupuestoActual;

        if (!$p || !in_array($p->estado?->epr_codigo, EstadoPresupuesto::VISIBLES_CLIENTE, true)) {
            return null;
        }

        $vencido = $p->pse_fecven && $p->pse_fecven->lt(today());

        return [
            'pse_numero'  => $p->pse_numero,
            'pse_versio'  => $p->pse_versio,
            'estado'      => $p->estado->epr_descri,
            'codigo'      => $p->estado->epr_codigo,
            'pse_fecven'  => $p->pse_fecven?->format('Y-m-d'),
            'pse_obs'     => $p->pse_obs,
            'pse_motrec'  => $p->pse_motrec,
            // Paso 2 (E.14): cómo se registró la respuesta, sin exponer al funcionario.
            'respuesta'   => $p->pse_respon ? [
                'fecha'    => $p->pse_respon->format('Y-m-d H:i'),
                'canal'    => $p->pse_canres,
                'etiqueta' => \App\Models\Servicio\PresupuestoServicio::ETIQUETAS_RESPUESTA[$p->pse_canres] ?? null,
                'asistida' => $p->pse_canres !== null && $p->pse_canres !== 'WEB',
            ] : null,
            'pse_impues'  => (float) $p->pse_impues,
            'pse_total'   => (float) $p->pse_total,
            'costo_retiro' => (float) $this->sse_cosret,
            'puede_responder' => $p->estado->epr_codigo === EstadoPresupuesto::ENVIADO && !$vencido,
            'lineas' => $p->detalles->map(fn ($l) => [
                'tipo'   => $l->psd_tipo,
                'descri' => $l->psd_descri,
                'cantid' => (float) $l->psd_cantid,
                'precio' => (float) $l->psd_precio,
                'total'  => (float) $l->psd_total,
            ]),
        ];
    }

    /**
     * Lo que el cliente ve de la gestión con la empresa: quién, cuándo y cómo terminó.
     * El contacto y las observaciones internas no se exponen (salvo el motivo si no la aceptó).
     */
    private function garantiaEmpresaVisible(): ?array
    {
        $g = $this->garantiaProveedor;
        if (!$g) {
            return null;
        }

        return [
            'empresa'          => $g->proveedor?->pro_razon,
            'nrocaso'          => $g->gpr_nrocaso,
            'fecha_envio'      => $g->gpr_fecenv?->format('Y-m-d'),
            'fecha_recepcion'  => $g->gpr_fecrec?->format('Y-m-d'),
            'resultado'        => $g->gpr_result ? GarantiaProveedor::RESULTADOS[$g->gpr_result] : null,
            'codigo_resultado' => $g->gpr_result,
            'nuevo_serie'      => $g->gpr_result === GarantiaProveedor::REEMPLAZADO ? $g->gpr_nroserr : null,
            'motivo'           => $g->gpr_result === GarantiaProveedor::RECHAZADO ? $g->gpr_obs : null,
        ];
    }

    private function resultadoVisible(): ?array
    {
        $o = $this->ordenServicio;
        if (!$o || !$o->ose_result) {
            return null;
        }

        return [
            'resultado' => ['REPARADO' => 'Reparado', 'PARCIAL' => 'Reparación parcial', 'NO_REPARABLE' => 'Devuelto sin reparar'][$o->ose_result],
            'detalle'   => $o->ose_proced,
            'total'     => (float) $this->sse_total,
        ];
    }

    /** Garantía del trabajo: corre desde la entrega. */
    private function garantiaVisible(): ?array
    {
        $vence = $this->garantiaVenceEl();
        if (!$vence) {
            return null;
        }

        $reclamoAbierto = $this->relationLoaded('reclamos') && $this->reclamos->contains(
            fn ($r) => !in_array($r->estadoServicio?->ets_codigo, ['ENTREGADA', 'CANCELADA'], true)
        );

        return [
            'dias'           => $this->ordenServicio->ose_gardia,
            'vence'          => $vence->format('Y-m-d'),
            'vigente'        => $vence->isFuture(),
            'puede_reclamar' => $vence->isFuture() && !$reclamoAbierto,
            'reclamo_abierto'=> $reclamoAbierto,
        ];
    }

    /**
     * Bloque de pago de C4. 'pagable' lo decide el backend (LISTA, con saldo y
     * sin garantía): el front solo muestra u oculta el botón "Pagar".
     */
    private function pagoVisible(): ?array
    {
        $estadoPago = app(EstadoPagoServicioService::class);

        if (!$estadoPago->admitePagos($this->resource) && $this->pagos->isEmpty()) {
            return null;
        }

        $resumen = $estadoPago->resumen($this->resource);

        return [
            'estado'  => $this->estadoPago?->epa_descri,
            'total'   => $resumen['total'],
            'pagado'  => $resumen['confirmado'],
            'en_verificacion' => $resumen['en_verificacion'],
            'saldo'   => $resumen['saldo'],
            'pagable' => $this->estadoServicio?->ets_codigo === 'LISTA'
                && $resumen['admite_pagos'] && $resumen['saldo'] > 0,
            'pagos'   => $this->pagos->map(fn ($p) => (new ServicioPagoClienteResource($p, $this->sse_numero))->resolve())->values(),
        ];
    }

    private function accionPendiente(): ?string
    {
        return match ($this->estadoServicio?->ets_codigo) {
            'SOLICITADA' => $this->esADomicilio()
                ? 'Esperá la confirmación del retiro de tu equipo'
                : 'Llevá tu equipo a la sucursal elegida',
            'PRESUPUESTADA' => 'Revisá y respondé el presupuesto',
            'LISTA'         => match ($this->estadoPago?->epa_codigo) {
                EstadoPago::RECHAZADO      => 'Revisá tu pago: necesitamos un comprobante nuevo',
                EstadoPago::PENDIENTE_PAGO => 'Tu equipo está listo: pagá el servicio o abonalo al retirar',
                EstadoPago::PAGO_VERIFICAR => 'Estamos verificando tu pago',
                default                    => 'Tu equipo está listo para retirar',
            },
            default         => null,
        };
    }
}

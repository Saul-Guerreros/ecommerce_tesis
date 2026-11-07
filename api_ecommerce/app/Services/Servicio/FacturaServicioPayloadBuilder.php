<?php

namespace App\Services\Servicio;

use App\Exceptions\EmisionFacturaException;
use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Venta\VentaCab;
use App\Services\Facturacion\FacturaPayloadBuilder;
use App\Services\Ruc\DigitoVerificador;

/**
 * Builder del cuerpo de POST /facturas que reconoce el ORIGEN de la venta.
 *
 *   - Pedido (ven_sseid NULL): delega en parent::construir(), sin cambios.
 *   - Servicio técnico: arma su JSON (guía 0.54.1, cap. 6.6.A y 29):
 *       ANTICIPO  tipo_transaccion 9, ítems por el importe del adelanto,
 *                 pagos = medios declarados del anticipo.
 *       VENTA     tipo 2/3; si aplica un anticipo: anticipo { cdc, condicion 2 }
 *                 e items[].anticipo POR UNIDAD; pagos = medios del NETO;
 *                 neto cero -> pagos [{ tipo 11, monto 0 }].
 *     Siempre entrega_inmediata: true y enviar_correo: false: el correo se
 *     pide DESPUÉS del cobro (FacturaCorreoService).
 *
 * Pagos DECLARADOS (no rechazados), no solo confirmados: la factura se emite
 * antes del cobro, igual que en pedidos. Una vez emitida, sus pagos no se
 * rechazan (lo impide la Fase 3), así un reintento manda lo mismo.
 *
 * Todo sale del SNAPSHOT de venta_cab / venta_detalle.
 */
class FacturaServicioPayloadBuilder extends FacturaPayloadBuilder
{
    /** mpa_codigo del sistema => pagos[].tipo de la API (guía 6.6). */
    private const MEDIO_PAGO_SERVICIO = [
        'CONTRAENTREGA'   => 1,   // efectivo
        'TRANSFERENCIA'   => 5,
        'BILLETERA_MOVIL' => 7,   // billetera electrónica (giros Tigo)
    ];

    /** pagos[].tipo 11: pago por anticipo, monto 0 en una final de total cero. */
    private const PAGO_POR_ANTICIPO = 11;

    /** anticipo.condicion 2: aplicación por ítem (items[].anticipo). */
    private const ANTICIPO_POR_ITEM = 2;

    private const TOLERANCIA_SERVICIO = 0.01;

    private const TRANSACCION_SERVICIOS = 2;
    private const TRANSACCION_MIXTO     = 3;
    private const TRANSACCION_ANTICIPO  = 9;

    public function construir(VentaCab $venta): array
    {
        if ($venta->ven_sseid === null) {
            return parent::construir($venta);
        }

        return $this->construirServicio($venta);
    }

    // ---------- Servicio técnico ----------

    /** @return array<string, mixed> */
    private function construirServicio(VentaCab $venta): array
    {
        if ($venta->ven_creado === null) {
            $venta->refresh();
        }

        $venta->loadMissing(['detalle', 'sucursal', 'anticipoAplicado.fe']);

        $solicitud = SolicitudServicio::with(['pagos.metodoPago', 'pagos.estadoPago'])
            ->find($venta->ven_sseid);

        if (!$solicitud) {
            throw new EmisionFacturaException(
                "La factura {$venta->ven_nrodoc} apunta a una solicitud de servicio que no existe."
            );
        }

        $items = $this->itemsServicio($venta);
        $this->verificarTotalServicio($venta, $items);

        $payload = [
            'sede'             => $this->sedeServicio($venta),
            'numero'           => (string) $venta->ven_numero,
            'timbrado'         => $venta->ven_timbrado,
            'timbrado_inicio'  => $venta->ven_timb_inicio->toDateString(),
            'timbrado_fin'     => $venta->ven_timb_venc->toDateString(),
            'fecha'            => $venta->ven_creado->format('Y-m-d\TH:i:s'),   // hora PY, sin zona
            'condicion'        => 'contado',
            'tipo_transaccion' => $this->tipoTransaccionServicio($venta),
            'cliente'          => $this->clienteServicio($venta),
        ];

        if ($venta->ven_antvenid !== null) {
            $payload['anticipo'] = $this->anticipoAplicado($venta);
        }

        return $payload + [
            'items'             => $items,
            'pagos'             => $this->pagosServicio($venta, $solicitud),
            'entrega_inmediata' => true,
            'enviar_correo'     => false,
        ];
    }

    /** @return array<string, string> */
    private function sedeServicio(VentaCab $venta): array
    {
        $sede = [
            'establecimiento' => $venta->ven_establec,
            'punto'           => $venta->ven_puntoexp,
        ];

        $direccion = trim((string) $venta->sucursal?->suc_direccion);
        if ($direccion !== '') {
            $sede['direccion'] = $direccion;
        }

        return $sede;
    }

    /** @return array<string, string> */
    private function clienteServicio(VentaCab $venta): array
    {
        if ($venta->ven_ruc) {
            $cliente = [
                'tipo'      => 'RUC',
                'documento' => $this->rucServicio((string) $venta->ven_ruc, $venta),
                'nombre'    => $venta->ven_razonsocial,
            ];
        } else {
            $cedula = preg_replace('/\D/', '', (string) $venta->ven_ci);

            if ($cedula === '') {
                throw new EmisionFacturaException(
                    "La factura {$venta->ven_nrodoc} no tiene un número de cédula válido."
                );
            }

            $cliente = ['tipo' => 'CI', 'documento' => $cedula, 'nombre' => $venta->ven_razonsocial];
        }

        if ($venta->ven_email && filter_var($venta->ven_email, FILTER_VALIDATE_EMAIL)) {
            $cliente['email'] = $venta->ven_email;
        }

        return $cliente;
    }

    private function rucServicio(string $ruc, VentaCab $venta): string
    {
        $ruc = trim($ruc);

        if (!preg_match('/^([0-9]{1,15})-([0-9])$/', $ruc, $m)
            || DigitoVerificador::digitoVerificador($m[1]) !== (int) $m[2]) {
            throw new EmisionFacturaException(
                "El RUC {$ruc} de la factura {$venta->ven_nrodoc} no es válido (formato o dígito verificador)."
            );
        }

        return $ruc;
    }

    /**
     * Precio unitario cobrado (IVA incluido, ya con descuento comercial) y,
     * en la final, el anticipo aplicado POR UNIDAD (guía 29).
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemsServicio(VentaCab $venta): array
    {
        return $venta->detalle->map(function ($d) {
            $item = [
                'codigo'          => (string) $d->vnd_codigo,
                'descripcion'     => $d->vnd_descripcion,
                'cantidad'        => (float) $d->vnd_cantidad,
                'precio_unitario' => (float) $d->vnd_precio,
                'iva'             => (int) $d->vnd_tasa,
            ];

            if ((float) $d->vnd_anticipo > 0) {
                $item['anticipo'] = (float) $d->vnd_anticipo;
            }

            return $item;
        })->values()->all();
    }

    private function tipoTransaccionServicio(VentaCab $venta): int
    {
        if ($venta->esAnticipo()) {
            return self::TRANSACCION_ANTICIPO;
        }

        return $venta->detalle->contains(fn ($d) => $d->vnd_prdid !== null)
            ? self::TRANSACCION_MIXTO
            : self::TRANSACCION_SERVICIOS;
    }

    /**
     * anticipo { cdc, condicion } de la final. En producción el antecedente
     * tiene que estar APROBADO (guía 29: PREPARADO no alcanza); en simulado
     * y test la aprobación local también deja venta_fe en ENVIADO.
     *
     * @return array{cdc: string, condicion: int}
     */
    private function anticipoAplicado(VentaCab $venta): array
    {
        $anticipo = $venta->anticipoAplicado;

        if (!$anticipo || !$anticipo->esAnticipo() || !$anticipo->esVigente()) {
            throw new EmisionFacturaException(
                "La factura {$venta->ven_nrodoc} referencia un anticipo inexistente o anulado."
            );
        }

        $fe = $anticipo->fe;

        if (!$fe || !$fe->estaEnviado() || $fe->vfe_cdc === null) {
            throw new EmisionFacturaException(
                "La factura de anticipo {$anticipo->ven_nrodoc} todavía no está aprobada: "
                . 'la factura final no puede aplicarla.'
            );
        }

        return ['cdc' => $fe->vfe_cdc, 'condicion' => self::ANTICIPO_POR_ITEM];
    }

    /**
     * Medios de pago (guía 6.6 y 29):
     *   - ANTICIPO: los pagos ANTICIPO declarados; suman ven_total.
     *   - VENTA: los demás pagos declarados; suman el NETO (ven_total).
     *   - VENTA con neto cero: un único { tipo 11, monto 0 }.
     *
     * @return array<int, array{tipo: int, monto: float}>
     */
    private function pagosServicio(VentaCab $venta, SolicitudServicio $s): array
    {
        $total = (float) $venta->ven_total;

        if (!$venta->esAnticipo() && $total <= self::TOLERANCIA_SERVICIO) {
            if ($venta->ven_antvenid === null) {
                throw new EmisionFacturaException(
                    "La factura {$venta->ven_nrodoc} tiene total cero y no aplica un anticipo."
                );
            }

            return [['tipo' => self::PAGO_POR_ANTICIPO, 'monto' => 0]];
        }

        $declarados = $s->pagos
            ->filter(fn ($p) => $p->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO
                             && $p->esAnticipo() === $venta->esAnticipo())
            ->sortBy('spa_orden');

        $salida = [];
        $suma   = 0.0;

        foreach ($declarados as $pago) {
            $codigo = $pago->metodoPago?->mpa_codigo;
            $tipo   = self::MEDIO_PAGO_SERVICIO[$codigo] ?? null;

            if ($tipo === null) {
                throw new EmisionFacturaException(
                    "El medio de pago \"{$codigo}\" no tiene equivalente en la API de facturación. "
                    . 'Agregalo a FacturaServicioPayloadBuilder::MEDIO_PAGO_SERVICIO.'
                );
            }

            $monto    = round((float) $pago->spa_monto, 2);
            $salida[] = ['tipo' => $tipo, 'monto' => $monto];
            $suma    += $monto;
        }

        if ($salida === [] || abs(round($suma, 2) - $total) > self::TOLERANCIA_SERVICIO) {
            throw new EmisionFacturaException(sprintf(
                'Los pagos declarados de la factura %s suman Gs. %s y su total es Gs. %s.',
                $venta->ven_nrodoc,
                number_format($suma, 0, ',', '.'),
                number_format($total, 0, ',', '.')
            ));
        }

        return $salida;
    }

    /**
     * La API calcula el total con los ítems: suma de cantidad × (precio −
     * anticipo) tiene que dar ven_total, y lo aplicado tiene que dar ven_antapl.
     */
    private function verificarTotalServicio(VentaCab $venta, array $items): void
    {
        $neto     = 0.0;
        $aplicado = 0.0;

        foreach ($items as $item) {
            $anticipo  = (float) ($item['anticipo'] ?? 0);
            $neto     += $item['cantidad'] * ($item['precio_unitario'] - $anticipo);
            $aplicado += $item['cantidad'] * $anticipo;
        }

        if (abs(round($neto, 2) - (float) $venta->ven_total) > self::TOLERANCIA_SERVICIO) {
            throw new EmisionFacturaException(sprintf(
                'La factura %s no se puede declarar: el detalle suma Gs. %s y el total registrado es Gs. %s.',
                $venta->ven_nrodoc,
                number_format($neto, 0, ',', '.'),
                number_format((float) $venta->ven_total, 0, ',', '.')
            ));
        }

        if (abs(round($aplicado, 2) - (float) $venta->ven_antapl) > self::TOLERANCIA_SERVICIO) {
            throw new EmisionFacturaException(sprintf(
                'La factura %s aplica Gs. %s de anticipo en el detalle y Gs. %s en la cabecera.',
                $venta->ven_nrodoc,
                number_format($aplicado, 0, ',', '.'),
                number_format((float) $venta->ven_antapl, 0, ',', '.')
            ));
        }
    }
}
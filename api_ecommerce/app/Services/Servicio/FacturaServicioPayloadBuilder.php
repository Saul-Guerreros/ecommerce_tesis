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
 *   - Venta de un pedido (ven_sseid NULL): delega en parent::construir().
 *     El JSON de los pedidos sale idéntico, byte por byte. Es importante:
 *     un pedido PENDIENTE que se reintenta con el mismo número y otro
 *     contenido recibiría 409 contenido_distinto.
 *   - Venta de un servicio técnico (ven_sseid): arma su propio JSON.
 *
 * Se registra en AppServiceProvider como implementación de
 * FacturaPayloadBuilder. Así FacturacionService, el job
 * DeclararFacturaElectronica, el comando facturacion:reintentar y el botón
 * "Reintentar" de facturas-venta funcionan con los dos orígenes SIN
 * modificar ninguno de esos archivos (principio abierto/cerrado).
 *
 * Los métodos y constantes privados del padre no se pueden usar desde una
 * subclase en PHP: por eso la tabla de medios de pago y la validación del
 * RUC se repiten acá (duplicación controlada, a cambio de no tocar pedidos).
 *
 * Igual que el padre: todo sale del SNAPSHOT de venta_cab / venta_detalle,
 * así un reintento manda exactamente lo mismo. Los pagos salen de
 * servicio_pago, que ya no cambia una vez que el cobro quedó CONFIRMADO
 * (no admite pagos nuevos y un pago confirmado no se rechaza).
 */
class FacturaServicioPayloadBuilder extends FacturaPayloadBuilder
{
    /** mpa_codigo del sistema => pagos[].tipo de la API (guía 6.6). */
    private const MEDIO_PAGO_SERVICIO = [
        'CONTRAENTREGA'   => 1,   // efectivo
        'TRANSFERENCIA'   => 5,
        'BILLETERA_MOVIL' => 7,   // billetera electrónica (giros Tigo)
    ];

    private const TOLERANCIA_SERVICIO = 0.01;

    /** guía 6.6, tipo_transaccion */
    private const TRANSACCION_SERVICIOS = 2;
    private const TRANSACCION_MIXTO     = 3;

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
        // ven_creado lo pone la base: tras un create() el modelo no lo trae.
        if ($venta->ven_creado === null) {
            $venta->refresh();
        }

        $venta->loadMissing(['detalle', 'sucursal']);

        $solicitud = SolicitudServicio::with(['pagos.metodoPago', 'pagos.estadoPago', 'facturacion'])
            ->find($venta->ven_sseid);

        if (!$solicitud) {
            throw new EmisionFacturaException(
                "La factura {$venta->ven_nrodoc} apunta a una solicitud de servicio que no existe."
            );
        }

        $items = $this->itemsServicio($venta);
        $this->verificarTotalServicio($venta, $items);

        return [
            'sede'             => $this->sedeServicio($venta),
            'numero'           => (string) $venta->ven_numero,
            'timbrado'         => $venta->ven_timbrado,
            'timbrado_inicio'  => $venta->ven_timb_inicio->toDateString(),
            'timbrado_fin'     => $venta->ven_timb_venc->toDateString(),
            'fecha'            => $venta->ven_creado->format('Y-m-d\TH:i:s'),   // hora PY, sin zona
            'condicion'        => 'contado',
            'tipo_transaccion' => $this->tipoTransaccionServicio($venta),
            'cliente'          => $this->clienteServicio($venta),
            'items'            => $items,
            'pagos'            => $this->pagosServicio($venta, $solicitud),
            'enviar_correo'    => $this->enviarCorreoServicio($venta, $solicitud),
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

    /** @return array<int, array<string, mixed>> */
    private function itemsServicio(VentaCab $venta): array
    {
        return $venta->detalle->map(fn ($d) => [
            'codigo'          => (string) $d->vnd_codigo,
            'descripcion'     => $d->vnd_descripcion,
            'cantidad'        => (float) $d->vnd_cantidad,
            'precio_unitario' => (float) $d->vnd_precio,   // cobrado, IVA incluido
            'iva'             => (int) $d->vnd_tasa,       // 10, 5 o 0
        ])->all();
    }

    /**
     * 2 = prestación de servicios (mano de obra, revisión, retiro, envío).
     * 3 = mixto, si además se vendieron repuestos (las únicas líneas con
     * producto). Criterio fiscal a confirmar con el contador.
     */
    private function tipoTransaccionServicio(VentaCab $venta): int
    {
        return $venta->detalle->contains(fn ($d) => $d->vnd_prdid !== null)
            ? self::TRANSACCION_MIXTO
            : self::TRANSACCION_SERVICIOS;
    }

    /**
     * Medios de pago de la factura al contado (guía 6.6 y 17): un renglón por
     * cada pago CONFIRMADO, en el orden en que se registraron. Un cobro
     * mixto (p. ej. mitad transferencia y mitad efectivo) sale como dos
     * renglones, cada uno con su medio y su monto.
     *
     * Se usa spa_monto (lo asignado al pago), el mismo monto que suma
     * EstadoPagoServicioService para decidir CONFIRMADO. El excedente de un
     * comprobante (spa_montver mayor) no es parte del precio.
     *
     * Si lo confirmado supera el total (p. ej. un anticipo mayor que el total
     * final porque se usaron menos repuestos), se declara solo hasta el total:
     * los últimos pagos se recortan. La devolución del excedente es de una
     * etapa posterior y no cambia lo que se vendió.
     *
     * @return array<int, array{tipo: int, monto: float}>
     */
    private function pagosServicio(VentaCab $venta, SolicitudServicio $s): array
    {
        $confirmados = $s->pagos
            ->filter(fn ($p) => $p->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO)
            ->sortBy('spa_orden');

        $restante = (float) $venta->ven_total;
        $salida   = [];

        foreach ($confirmados as $pago) {
            if ($restante <= self::TOLERANCIA_SERVICIO) {
                break;
            }

            $codigo = $pago->metodoPago?->mpa_codigo;
            $tipo   = self::MEDIO_PAGO_SERVICIO[$codigo] ?? null;

            if ($tipo === null) {
                throw new EmisionFacturaException(
                    "El medio de pago \"{$codigo}\" no tiene equivalente en la API de facturación. "
                    . 'Agregalo a FacturaServicioPayloadBuilder::MEDIO_PAGO_SERVICIO.'
                );
            }

            $monto    = round(min((float) $pago->spa_monto, $restante), 2);
            $salida[] = ['tipo' => $tipo, 'monto' => $monto];
            $restante = round($restante - $monto, 2);
        }

        if ($salida === [] || $restante > self::TOLERANCIA_SERVICIO) {
            throw new EmisionFacturaException(sprintf(
                'Los pagos confirmados de la factura %s no cubren el total (faltan Gs. %s).',
                $venta->ven_nrodoc,
                number_format(max($restante, 0), 0, ',', '.')
            ));
        }

        return $salida;
    }

    /**
     * Igual que el pedido: la API manda el correo solo si está habilitado
     * (FACTURACION_ENVIAR_CORREO), se eligió CORREO y hay un correo válido.
     * Sin datos fiscales elegidos (factura con la CI por defecto): no.
     */
    private function enviarCorreoServicio(VentaCab $venta, SolicitudServicio $s): bool
    {
        if (!config('facturacion.enviar_correo')) {
            return false;
        }

        return (bool) $s->facturacion?->recibePorCorreo()
            && filter_var($venta->ven_email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** La API calcula el total con los ítems: tiene que dar ven_total. */
    private function verificarTotalServicio(VentaCab $venta, array $items): void
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += $item['cantidad'] * $item['precio_unitario'];
        }

        if (abs(round($total, 2) - (float) $venta->ven_total) > self::TOLERANCIA_SERVICIO) {
            throw new EmisionFacturaException(sprintf(
                'La factura %s no se puede declarar: el detalle suma Gs. %s y el total registrado es Gs. %s.',
                $venta->ven_nrodoc,
                number_format($total, 0, ',', '.'),
                number_format((float) $venta->ven_total, 0, ',', '.')
            ));
        }
    }
}

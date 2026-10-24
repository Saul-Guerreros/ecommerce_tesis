<?php

namespace App\Services\Facturacion;

use App\Exceptions\EmisionFacturaException;
use App\Models\Pedido\EstadoPago;
use App\Models\Venta\VentaCab;
use App\Services\Ruc\DigitoVerificador;

/**
 * Convierte una factura interna (VentaCab) en el cuerpo JSON de
 * POST /facturas de Facturacion_API (guia de integracion, capitulos 6 y 9).
 *
 * Responsabilidad UNICA: armar el arreglo. No llama a la API, no guarda
 * nada y no cambia el estado de la venta. Asi se puede probar sola.
 *
 * Reglas que respeta:
 *   - Todo sale del SNAPSHOT de venta_cab / venta_detalle, nunca de las
 *     tablas vivas (cliente, producto, empresa). Un reintento tiene que
 *     mandar exactamente lo mismo que el primer intento: la API contesta
 *     409 contenido_distinto si el contenido cambia con el mismo numero.
 *   - El precio unitario es el COBRADO, con IVA incluido.
 *   - Si algo no cierra (total, medio de pago, RUC), lanza
 *     EmisionFacturaException ANTES de llamar a la API.
 *
 * Lo que NO manda, a proposito:
 *   - emisor: lo toma de Portal -> Empresa -> Datos.
 *   - direccion del cliente: la API la exige completa (con los codigos de
 *     departamento, distrito y ciudad de la DNIT) o no la declara.
 *   - telefono del cliente: opcional y sin utilidad fiscal.
 *   - enviar_correo: false. Quien envia el comprobante al cliente se
 *     decide mas adelante; hasta entonces no puede llegarle duplicado.
 */
class FacturaPayloadBuilder
{
    /** mpa_codigo del sistema => pagos[].tipo de la API (guia 6.6). */
    private const MEDIO_PAGO = [
        'CONTRAENTREGA'   => 1,   // efectivo
        'TRANSFERENCIA'   => 5,
        'BILLETERA_MOVIL' => 7,   // billetera electronica
    ];

    /** Diferencia aceptada (en Gs.) por redondeos entre sistemas. */
    private const TOLERANCIA = 0.01;

    /**
     * @return array<string, mixed>
     *
     * @throws EmisionFacturaException
     */
    public function construir(VentaCab $venta): array
    {
        // ven_creado lo pone la base (DEFAULT): tras un create() el modelo
        // todavia no lo trae.
        if ($venta->ven_creado === null) {
            $venta->refresh();
        }

        $venta->loadMissing([
            'detalle',
            'sucursal',
            'pedido.pagos.metodoPago',
            'pedido.pagos.estadoPago',
            'pedido.facturacion',
        ]);

        $items = $this->items($venta);
        $this->verificarTotal($venta, $items);

        return [
            'sede'             => $this->sede($venta),
            'numero'           => (string) $venta->ven_numero,
            'timbrado'         => $venta->ven_timbrado,
            'timbrado_inicio'  => $venta->ven_timb_inicio->toDateString(),
            'timbrado_fin'     => $venta->ven_timb_venc->toDateString(),
            // Hora de Paraguay, sin zona horaria (guia 6.7).
            'fecha'            => $venta->ven_creado->format('Y-m-d\TH:i:s'),
            'condicion'        => 'contado',
            'tipo_transaccion' => $this->tipoTransaccion($items),
            'cliente'          => $this->cliente($venta),
            'items'            => $items,
            'pagos'            => $this->pagos($venta),
            'enviar_correo'    => $this->enviarCorreo($venta),
        ];
    }

    // ---------- Partes del documento ----------

    /** @return array<string, string> */
    private function sede(VentaCab $venta): array
    {
        $sede = [
            'establecimiento' => $venta->ven_establec,
            'punto'           => $venta->ven_puntoexp,
        ];

        // Con direccion + timbrado + vigencia, una sede que la API todavia
        // no conoce se carga sola (guia 9.1).
        $direccion = trim((string) $venta->sucursal?->suc_direccion);

        if ($direccion !== '') {
            $sede['direccion'] = $direccion;
        }

        return $sede;
    }

    /** @return array<string, string> */
    private function cliente(VentaCab $venta): array
    {
        if ($venta->ven_ruc) {
            $cliente = [
                'tipo'      => 'RUC',
                'documento' => $this->rucValido((string) $venta->ven_ruc),
                'nombre'    => $venta->ven_razonsocial,
            ];
        } else {
            $cedula = preg_replace('/\D/', '', (string) $venta->ven_ci);

            if ($cedula === '') {
                throw new EmisionFacturaException(
                    "La factura {$venta->ven_nrodoc} no tiene un número de cédula válido."
                );
            }

            $cliente = [
                'tipo'      => 'CI',
                'documento' => $cedula,   // sin puntos (guia 6.7)
                'nombre'    => $venta->ven_razonsocial,
            ];
        }

        if ($venta->ven_email && filter_var($venta->ven_email, FILTER_VALIDATE_EMAIL)) {
            $cliente['email'] = $venta->ven_email;
        }

        return $cliente;
    }

    /**
     * La API exige el RUC con su digito verificador y lo recalcula. Se
     * comprueba aca primero: un RUC mal cargado en una factura ya emitida
     * no se puede corregir (venta_cab es inmutable).
     */
    private function rucValido(string $ruc): string
    {
        $ruc = trim($ruc);

        if (!preg_match('/^([0-9]{1,15})-([0-9])$/', $ruc, $m)) {
            throw new EmisionFacturaException(
                "El RUC {$ruc} no tiene el formato correcto: debe incluir el dígito verificador (ej. 80069563-1)."
            );
        }

        if (DigitoVerificador::digitoVerificador($m[1]) !== (int) $m[2]) {
            throw new EmisionFacturaException(
                "El dígito verificador del RUC {$ruc} no es correcto."
            );
        }

        return $ruc;
    }

    /** @return array<int, array<string, mixed>> */
    private function items(VentaCab $venta): array
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
     * 1 = mercaderia, 3 = mixto (mercaderia y servicios).
     * El costo de envio es la unica linea de servicio de la factura.
     * Es un criterio fiscal para confirmar con el contador de la tienda.
     */
    private function tipoTransaccion(array $items): int
    {
        foreach ($items as $item) {
            if ($item['codigo'] === 'ENVIO') {
                return 3;
            }
        }

        return 1;
    }

    /**
     * Al contado la API pide medios de pago explicitos (guia 17).
     * Se excluyen los pagos RECHAZADOS: no entraron plata.
     *
     * @return array<int, array{tipo: int, monto: float}>
     */
    private function pagos(VentaCab $venta): array
    {
        $pagos = ($venta->pedido?->pagos ?? collect())
            ->filter(fn ($p) => $p->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO)
            ->sortBy('ppa_orden');

        if ($pagos->isEmpty()) {
            throw new EmisionFacturaException(
                "La factura {$venta->ven_nrodoc} no tiene pagos registrados."
            );
        }

        $salida = [];
        $suma   = 0.0;

        foreach ($pagos as $pago) {
            $codigo = $pago->metodoPago?->mpa_codigo;
            $tipo   = self::MEDIO_PAGO[$codigo] ?? null;

            if ($tipo === null) {
                throw new EmisionFacturaException(
                    "El medio de pago \"{$codigo}\" no tiene equivalente en la API de facturación. "
                    . 'Agregalo a FacturaPayloadBuilder::MEDIO_PAGO.'
                );
            }

            $monto    = (float) $pago->ppa_monto;
            $salida[] = ['tipo' => $tipo, 'monto' => $monto];
            $suma    += $monto;
        }

        if (abs($suma - (float) $venta->ven_total) > self::TOLERANCIA) {
            throw new EmisionFacturaException(sprintf(
                'Los pagos de la factura %s suman Gs. %s y el total es Gs. %s.',
                $venta->ven_nrodoc,
                number_format($suma, 0, ',', '.'),
                number_format((float) $venta->ven_total, 0, ',', '.')
            ));
        }

        return $salida;
    }


        /**
     * Se le pide a la API que mande la factura al cliente solo si:
     *   - el envio esta habilitado (FACTURACION_ENVIAR_CORREO),
     *   - el cliente eligio recibirla por CORREO en el checkout, y
     *   - dejo un correo valido.
     * En cualquier otro caso va false: ni la API ni nadie le escribe.
     */
    private function enviarCorreo(VentaCab $venta): bool
    {
        if (!config('facturacion.enviar_correo')) {
            return false;
        }

        return $venta->pedido?->facturacion?->pfa_recibe === 'CORREO'
            && filter_var($venta->ven_email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // ---------- Controles ----------

    /**
     * La API calcula el total con los items: suma de cantidad x precio
     * cobrado. Si no coincide con el total que este sistema registro, lo
     * que se declararia ante la DNIT seria otro importe.
     */
    private function verificarTotal(VentaCab $venta, array $items): void
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $item['cantidad'] * $item['precio_unitario'];
        }

        $total = round($total, 2);

        if (abs($total - (float) $venta->ven_total) > self::TOLERANCIA) {
            throw new EmisionFacturaException(sprintf(
                'La factura %s no se puede declarar: el detalle suma Gs. %s pero el total registrado es Gs. %s. '
                . 'Revisá precios, recargos y descuentos del pedido.',
                $venta->ven_nrodoc,
                number_format($total, 0, ',', '.'),
                number_format((float) $venta->ven_total, 0, ',', '.')
            ));
        }
    }
}
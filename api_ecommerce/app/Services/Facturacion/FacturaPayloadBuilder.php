<?php

namespace App\Services\Facturacion;

use App\Exceptions\EmisionFacturaException;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Pedido\PedidoPago;
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
        'CHEQUE'          => 2,   // cheque
        'TRANSFERENCIA'   => 5,
        'BILLETERA_MOVIL' => 7,   // billetera electronica
    ];

    /** Diferencia aceptada (en Gs.) por redondeos entre sistemas. */
    private const TOLERANCIA = 0.01;

    /** Largo que la API exige para cheque.banco (guia 6.6). */
    private const BANCO_MIN = 4;
    private const BANCO_MAX = 20;

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
            'pedido.pagos.cheque',
            'pedido.facturacion',
            'cuotas',
        ]);

        $items = $this->items($venta);
        $this->verificarTotal($venta, $items);

        $documento = [
            'sede'             => $this->sede($venta),
            'numero'           => (string) $venta->ven_numero,
            'timbrado'         => $venta->ven_timbrado,
            'timbrado_inicio'  => $venta->ven_timb_inicio->toDateString(),
            'timbrado_fin'     => $venta->ven_timb_venc->toDateString(),
            // Hora de Paraguay, sin zona horaria (guia 6.7).
            'fecha'            => $venta->ven_creado->format('Y-m-d\TH:i:s'),
            // Una venta a credito no lleva medios de pago: todavia no entro
            // plata. En su lugar el documento declara el plan de cuotas.
            'condicion'        => $venta->esACredito() ? 'credito' : 'contado',
            'tipo_transaccion' => $this->tipoTransaccion($items),
            'cliente'          => $this->cliente($venta),
            'items'            => $items,
            ...($venta->esACredito()
                ? ['credito' => $this->credito($venta)]
                : ['pagos'   => $this->pagos($venta)]),
            'enviar_correo'    => $this->enviarCorreo($venta),
        ];

        // Servicio tecnico (factura o anticipo): se declara su propia actividad
        // economica; sin esto la API usa la predeterminada (comercio).
        if ($actividad = $this->actividadServicio($venta)) {
            $documento['actividad_id'] = $actividad;
        }

        return $documento;
    }

    /** ID interno de Facturacion_API (GET /actividades), no el codigo DNIT. Guia cap. 20. */
    private function actividadServicio(VentaCab $venta): ?int
    {
        $id = config('facturacion.actividad_servicio_id');

        return $venta->ven_sseid !== null && $id !== null ? (int) $id : null;
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
     * Cuando el medio es cheque, la API exige ademas el objeto "cheque"
     * con numero y banco: sin eso devuelve 422 comprobante_invalido.
     *
     * @return array<int, array<string, mixed>>
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

            $monto = (float) $pago->ppa_monto;
            $linea = ['tipo' => $tipo, 'monto' => $monto];

            if ($codigo === 'CHEQUE') {
                $linea['cheque'] = $this->datosCheque($pago, $venta);
            }

            $salida[] = $linea;
            $suma    += $monto;
        }

          if (abs($suma - (float) $venta->ven_total) > self::TOLERANCIA) {
            throw new EmisionFacturaException(sprintf(
                'Los pagos de la factura %s suman Gs. %s y el total es Gs. %s.',
                $venta->ven_nrodoc,
                $this->gs($suma),
                $this->gs((float) $venta->ven_total)
            ));
        }

        return $salida;
    }

    /**
     * Datos del cheque que acompanian al pago tipo 2.
     *
     * Salen de pago_cheque, que es 1:1 con pedido_pago: con pago dividido
     * cada pago puede traer su propio cheque, por eso no se leen del
     * pedido ni de pedido_pago (ahi ppa_banco es el banco de la
     * transferencia, no el del cheque).
     *
     * @return array{numero: string, banco: string}
     */
    private function datosCheque(PedidoPago $pago, VentaCab $venta): array
    {
        $cheque = $pago->cheque;

        if ($cheque === null) {
            throw new EmisionFacturaException(sprintf(
                'El pago %d de la factura %s es con cheque pero no tiene los datos del cheque cargados.',
                $pago->ppa_orden,
                $venta->ven_nrodoc
            ));
        }

        return [
            'numero' => $this->numeroCheque($cheque, $pago, $venta),
            'banco'  => $this->bancoCheque($cheque, $pago, $venta),
        ];
    }

    /**
     * La API pide exactamente 8 digitos y conserva los ceros a la
     * izquierda: el cheque N 45678 se declara como "00045678".
     *
     * pch_nro es texto libre de 20, asi que puede venir con guiones o
     * espacios. Se limpia antes de medir.
     */
    private function numeroCheque(PagoCheque $cheque, PedidoPago $pago, VentaCab $venta): string
    {
        $original = trim((string) $cheque->pch_nro);
        $numero   = preg_replace('/\D/', '', $original);

        if ($numero === '') {
            throw new EmisionFacturaException(sprintf(
                'El cheque del pago %d de la factura %s no tiene número cargado. '
                . 'Completalo en la gestión del cheque antes de declarar la factura.',
                $pago->ppa_orden,
                $venta->ven_nrodoc
            ));
        }

        if (strlen($numero) > 8) {
            throw new EmisionFacturaException(sprintf(
                'El número de cheque "%s" del pago %d de la factura %s tiene %d dígitos: '
                . 'la DNIT admite hasta 8.',
                $original,
                $pago->ppa_orden,
                $venta->ven_nrodoc,
                strlen($numero)
            ));
        }

        return str_pad($numero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * pch_banco admite 80 caracteres y la API solo 20. Un nombre largo
     * ("Banco Continental S.A.E.C.A.") se recorta por palabra entera en
     * vez de bloquear la emision: el banco igual queda identificado.
     */
    private function bancoCheque(PagoCheque $cheque, PedidoPago $pago, VentaCab $venta): string
    {
        $banco = trim(preg_replace('/\s+/u', ' ', (string) $cheque->pch_banco));

        if (mb_strlen($banco) < self::BANCO_MIN) {
            throw new EmisionFacturaException(sprintf(
                'El banco del cheque del pago %d de la factura %s debe tener al menos %d caracteres. '
                . 'Cargá el nombre completo del banco (ej. "Banco Nacional de Fomento").',
                $pago->ppa_orden,
                $venta->ven_nrodoc,
                self::BANCO_MIN
            ));
        }

        if (mb_strlen($banco) > self::BANCO_MAX) {
            $corte   = mb_substr($banco, 0, self::BANCO_MAX);
            $espacio = mb_strrpos($corte, ' ');

            if ($espacio !== false && $espacio >= self::BANCO_MIN) {
                $corte = mb_substr($corte, 0, $espacio);
            }

            $banco = rtrim($corte, " .,-");
        }

        return $banco;
    }

    /**
     * Plan de cuotas de una venta a credito. Reemplaza a la seccion de
     * pagos: la plata todavia no entro, lo que se declara es la deuda.
     *
     * La API acepta UNA sola forma de credito: "plazo" (un pago diferido)
     * o "cuotas" (el cronograma). Mandar las dos devuelve 422
     * datos_invalidos en campos.credito (guia 6.6). Esta venta siempre va
     * en cuotas, asi que "plazo" no se envia nunca.
     *
     * Sin entrega inicial: el sistema no maneja anticipo, por eso tampoco
     * se manda "entrega" y el cronograma cubre el total.
     *
     * @return array<string, mixed>
     *
     * @throws PayloadInvalidoException
     */
    private function credito(VentaCab $venta): array
    {
        $cuotas = $venta->cuotas;

        if ($cuotas->isEmpty()) {
            throw new PayloadInvalidoException(
                "La factura {$venta->ven_nrodoc} es a crédito pero no tiene cuotas registradas."
            );
        }

        $suma = round((float) $cuotas->sum('cco_monto'), 2);

        if (abs($suma - (float) $venta->ven_total) > self::TOLERANCIA) {
            throw new PayloadInvalidoException(sprintf(
                'Las cuotas de la factura %s suman Gs. %s y el total es Gs. %s.',
                $venta->ven_nrodoc,
                $this->gs($suma),
                $this->gs((float) $venta->ven_total),
            ));
        }

        return [
            'cuotas' => $cuotas->map(function ($c) {
                $cuota = ['monto' => (float) $c->cco_monto];

                // Opcional para la DNIT, pero sin el el cliente no sabe
                // cuando paga: va siempre que exista.
                if ($c->cco_vencim !== null) {
                    $cuota['vencimiento'] = $c->cco_vencim->toDateString();
                }

                return $cuota;
            })->values()->all(),
        ];
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
                $this->gs($total),
                $this->gs((float) $venta->ven_total)
            ));
        }
    }
        /**
     * Importe para los mensajes de error. Va con decimales a proposito:
     * los descuadres de esta clase suelen ser de centavos, y redondeando
     * el mensaje muestra dos veces la misma cifra.
     */
    private function gs(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }
}

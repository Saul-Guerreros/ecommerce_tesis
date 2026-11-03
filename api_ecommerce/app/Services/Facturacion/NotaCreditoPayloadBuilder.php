<?php

namespace App\Services\Facturacion;

use App\Exceptions\EmisionFacturaException;
use App\Models\Compras\MotivoNotaCredito;
use App\Models\Venta\NotaCreditoVenta;
use App\Models\Venta\VentaCab;
use App\Services\Ruc\DigitoVerificador;

/**
 * Convierte una Nota de Credito interna (NotaCreditoVenta) en el cuerpo
 * JSON de POST /documentos con tipo "nota_credito" (guia, capitulo 11.1).
 *
 * Tiene DOS responsabilidades, y las dos son de armado, no de negocio:
 *
 *   1. validarEmision(): se ejecuta ANTES de tomar el numero del talonario.
 *      Comprueba todo lo que la DNIT exige y que este sistema puede saber
 *      sin llamar a la API: motivo con codigo fiscal, documento asociado
 *      disponible, identificacion del adquirente y fecha en plazo. Si algo
 *      falla, la NC no se emite y no se quema un numero.
 *
 *   2. construir(): arma el arreglo a partir de la NC ya grabada. Todo sale
 *      del SNAPSHOT (nota_credito_venta / det_nota_credito_venta), nunca de
 *      las tablas vivas: un reintento tiene que mandar exactamente lo mismo
 *      que el primer intento o la API contesta 409 contenido_distinto.
 *
 * Lo que NO lleva una nota de credito (guia 11, tabla de tipos):
 *   condicion, pagos, tipo_transaccion. Si lleva: cliente, motivo y
 *   asociado (obligatorio).
 */
class NotaCreditoPayloadBuilder
{
    /** Diferencia aceptada (en Gs.) por redondeos entre sistemas. */
    private const TOLERANCIA = 0.01;

    /** Ventana de la API para la fecha de emision: 720 horas hacia atras (guia 23.5). */
    private const DIAS_HACIA_ATRAS = 29;   // 30 dias menos un margen de seguridad

    // =================================================================
    //  1. Validacion previa (antes de numerar)
    // =================================================================

    /**
     * @throws EmisionFacturaException con un mensaje que le dice al usuario
     *         exactamente que corregir
     */
    public function validarEmision(VentaCab $factura, MotivoNotaCredito $motivo, string $fecha): void
    {
        $this->validarMotivo($motivo);
        $this->validarAsociado($factura);
        $this->validarAdquirente($factura);
        $this->validarFecha($fecha);
    }

    /** La DNIT no acepta texto libre: exige un codigo del 1 al 8 (guia 11.1). */
    private function validarMotivo(MotivoNotaCredito $motivo): void
    {
        if ($motivo->mnc_dnit === null) {
            throw new EmisionFacturaException(
                "El motivo \"{$motivo->mnc_descri}\" no tiene asignado un código de motivo de la DNIT. "
                . 'Cargalo en Configuración → Motivos de nota de crédito antes de emitir una NC electrónica.'
            );
        }
    }

    /**
     * asociado es OBLIGATORIO en una NC (guia 11). Dos caminos:
     *
     *   - Factura electronica aprobada -> se asocia por su CDC.
     *   - Factura anterior a la integracion (sin registro electronico) ->
     *     se asocia con los datos del impreso.
     *
     * Lo que NO se puede: asociar una factura que SI tiene registro
     * electronico pero todavia no fue aprobada. Sin CDC no hay forma valida
     * de referenciarla, y presentarla como impresa viola la validacion 2440
     * (guia 23.4).
     */
    private function validarAsociado(VentaCab $factura): void
    {
        $fe = $factura->fe;

        if ($fe === null) {
            return;   // factura anterior a la facturacion electronica: va como impresa
        }

        if ($fe->estaEnviado() && $this->esCdc($fe->vfe_cdc)) {
            return;
        }

        if ($fe->estaRechazado()) {
            throw new EmisionFacturaException(
                "La factura {$factura->ven_nrodoc} fue RECHAZADA por la DNIT: no existe como documento "
                . 'electrónico y no se le puede emitir una nota de crédito. Corregí la factura primero.'
            );
        }

        throw new EmisionFacturaException(
            "La factura {$factura->ven_nrodoc} todavía no fue aprobada por la DNIT. "
            . 'Una nota de crédito electrónica necesita el CDC de la factura para asociarse. '
            . 'Esperá a que la declaración se complete o reintentala desde el detalle de la factura.'
        );
    }

    /**
     * El adquirente de la NC es el MISMO que se declaro en la factura
     * (snapshot). Se valida aca porque un RUC mal cargado no se puede
     * corregir despues: la NC tambien es inmutable.
     */
    private function validarAdquirente(VentaCab $factura): void
    {
        $this->cliente($factura->ven_ruc, $factura->ven_ci, $factura->ven_razonsocial, $factura->ven_email);
    }

    /** Guia 23.5: la fecha de emision tiene que caer dentro de -720 h / +120 h. */
    private function validarFecha(string $fecha): void
    {
        $limite = now()->subDays(self::DIAS_HACIA_ATRAS)->toDateString();

        if ($fecha < $limite) {
            throw new EmisionFacturaException(
                "La fecha {$fecha} está fuera del plazo que acepta la DNIT para emitir un documento "
                . "electrónico (hasta 30 días hacia atrás, desde {$limite})."
            );
        }

        if ($fecha > now()->addDays(4)->toDateString()) {
            throw new EmisionFacturaException("La fecha {$fecha} está demasiado adelantada para emitir.");
        }
    }

    // =================================================================
    //  2. Armado del cuerpo
    // =================================================================

    /**
     * @return array<string, mixed>
     *
     * @throws EmisionFacturaException
     */
    public function construir(NotaCreditoVenta $nota): array
    {
        if ($nota->ncv_creado === null) {
            $nota->refresh();   // ncv_creado lo pone la base (DEFAULT)
        }

        $nota->loadMissing(['detalle', 'motivo', 'sucursal', 'factura.fe', 'factura.pedido.facturacion']);

        $factura = $nota->factura;

        if (!$factura) {
            throw new EmisionFacturaException("La nota de crédito {$nota->ncv_nrodoc} no tiene factura asociada.");
        }

        $items = $this->items($nota, $factura);
        $this->verificarTotal($nota, $items);

        return [
            'tipo'            => 'nota_credito',
            'sede'            => $this->sede($nota),
            'numero'          => (string) $nota->ncv_numero,
            'timbrado'        => $nota->ncv_timbrado,
            'timbrado_inicio' => $nota->ncv_timb_inicio->toDateString(),
            'timbrado_fin'    => $nota->ncv_timb_venc->toDateString(),
            'fecha'           => $this->fecha($nota),
            'motivo'          => (int) $nota->motivo->mnc_dnit,
            'asociado'        => $this->asociado($factura),
            'cliente'         => $this->cliente(
                $nota->ncv_ruc,
                $nota->ncv_ci,
                $nota->ncv_razonsocial,
                $factura->ven_email
            ),
            'items'           => $items,
            'enviar_correo'   => $this->enviarCorreo($factura),
        ];
    }

    // ---------- Partes del documento ----------

    /** @return array<string, string> */
    private function sede(NotaCreditoVenta $nota): array
    {
        $sede = [
            'establecimiento' => $nota->ncv_establec,
            'punto'           => $nota->ncv_puntoexp,
        ];

        $direccion = trim((string) $nota->sucursal?->suc_direccion);

        if ($direccion !== '') {
            $sede['direccion'] = $direccion;   // guia 9.1: una sede nueva se carga sola
        }

        return $sede;
    }

    /**
     * Hora de Paraguay, sin zona horaria (guia 6.7). Si la NC se fecha hoy
     * se usa el instante real de emision; si se fecha en un dia anterior
     * (permitido por el formulario) se usa el comienzo de ese dia.
     */
    private function fecha(NotaCreditoVenta $nota): string
    {
        $dia = $nota->ncv_fecha->toDateString();

        return $nota->ncv_creado->toDateString() === $dia
            ? $nota->ncv_creado->format('Y-m-d\TH:i:s')
            : $dia . 'T00:00:00';
    }

    /**
     * El documento que se corrige (guia 11.1 y 23.4).
     *
     * @return array<string, string>
     */
    private function asociado(VentaCab $factura): array
    {
        $cdc = $factura->fe?->vfe_cdc;

        if ($factura->fe?->estaEnviado() && $this->esCdc($cdc)) {
            return ['cdc' => $cdc];
        }

        // Factura anterior a la integracion: se referencia como impresa.
        return [
            'tipo'            => 'factura',
            'timbrado'        => $factura->ven_timbrado,
            'establecimiento' => $factura->ven_establec,
            'punto'           => $factura->ven_puntoexp,
            'numero'          => (string) (int) $factura->ven_numero,
            'fecha'           => $factura->ven_fchaem->toDateString(),
        ];
    }

    /** @return array<string, string> */
    private function cliente(?string $ruc, ?string $ci, string $nombre, ?string $email): array
    {
        if ($ruc) {
            $cliente = [
                'tipo'      => 'RUC',
                'documento' => $this->rucValido($ruc),
                'nombre'    => $nombre,
            ];
        } else {
            $cedula = preg_replace('/\D/', '', (string) $ci);

            if ($cedula === '') {
                throw new EmisionFacturaException(
                    'La factura no tiene un número de cédula válido para identificar al adquirente.'
                );
            }

            $cliente = ['tipo' => 'CI', 'documento' => $cedula, 'nombre' => $nombre];
        }

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $cliente['email'] = $email;
        }

        return $cliente;
    }

    private function rucValido(string $ruc): string
    {
        $ruc = trim($ruc);

        if (!preg_match('/^([0-9]{1,15})-([0-9])$/', $ruc, $m)) {
            throw new EmisionFacturaException(
                "El RUC {$ruc} no tiene el formato correcto: debe incluir el dígito verificador (ej. 80069563-1)."
            );
        }

        if (DigitoVerificador::digitoVerificador($m[1]) !== (int) $m[2]) {
            throw new EmisionFacturaException("El dígito verificador del RUC {$ruc} no es correcto.");
        }

        return $ruc;
    }

    /**
     * Como se describen los items (guia 11.1, regla explicita):
     *
     *   - Si la NC mueve mercaderia (devolucion, diferencia de cantidad), se
     *     acredita el documento o parte de el: van los MISMOS items, con su
     *     cantidad y su precio.
     *
     *   - Si NO mueve mercaderia (descuento, error de precio), se reintegra
     *     solo un importe: va UN item por tasa de IVA que diga sobre que
     *     documento y que productos. No se mandan los productos con precios
     *     prorrateados, porque en el KuDE se leerian como precios que nadie
     *     cobro.
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(NotaCreditoVenta $nota, VentaCab $factura): array
    {
        if ($nota->detalle->isEmpty()) {
            throw new EmisionFacturaException("La nota de crédito {$nota->ncv_nrodoc} no tiene ítems.");
        }

        return $nota->motivo->afectaStock()
            ? $this->itemsPorProducto($nota)
            : $this->itemsPorTasa($nota, $factura);
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsPorProducto(NotaCreditoVenta $nota): array
    {
        return $nota->detalle->map(fn ($d) => [
            'codigo'          => (string) $d->dnv_codigo,
            'descripcion'     => $d->dnv_descripcion,
            'cantidad'        => (float) $d->dnv_cantidad,
            'precio_unitario' => (float) $d->dnv_precio,   // acreditado, IVA incluido
            'iva'             => (int) $d->dnv_tasa,       // 10, 5 o 0
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsPorTasa(NotaCreditoVenta $nota, VentaCab $factura): array
    {
        $grupos = [];

        foreach ($nota->detalle as $d) {
            $tasa = (int) $d->dnv_tasa;

            $grupos[$tasa]['monto'][]       = (float) $d->dnv_valorventa;
            $grupos[$tasa]['productos'][]   = $d->dnv_descripcion;
        }

        krsort($grupos);   // 10, 5, 0: orden estable entre reintentos
        $items = [];

        foreach ($grupos as $tasa => $grupo) {
            $monto = round(array_sum($grupo['monto']), 2);

            if ($monto < 0.01) {
                continue;
            }

            $items[] = [
                'codigo'          => 'AJUSTE-' . $tasa,
                'descripcion'     => $this->descripcionAjuste($nota, $factura, $grupo['productos']),
                'cantidad'        => 1,
                'precio_unitario' => $monto,
                'iva'             => $tasa,
            ];
        }

        if ($items === []) {
            throw new EmisionFacturaException(
                "La nota de crédito {$nota->ncv_nrodoc} no tiene ningún importe a acreditar."
            );
        }

        return $items;
    }

    /** "Descuento posterior s/ factura 001-001-0000065: Teclado, Mouse" (max. 120 caracteres). */
    private function descripcionAjuste(NotaCreditoVenta $nota, VentaCab $factura, array $productos): string
    {
        $titulo = trim((string) $nota->motivo->mnc_descri);
        $lista  = implode(', ', array_unique($productos));
        $texto  = "{$titulo} s/ factura {$factura->ven_nrodoc}: {$lista}";

        return mb_strlen($texto) > 120 ? mb_substr($texto, 0, 117) . '...' : $texto;
    }

    /**
     * Mismo criterio que la factura: el correo sale solo si esta habilitado,
     * si el cliente eligio recibir el comprobante por CORREO y si dejo una
     * direccion valida.
     */
    private function enviarCorreo(VentaCab $factura): bool
    {
        if (!config('facturacion.enviar_correo')) {
            return false;
        }

        return $factura->pedido?->facturacion?->pfa_recibe === 'CORREO'
            && filter_var($factura->ven_email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // ---------- Controles ----------

    /**
     * La API calcula el total con los items: suma de cantidad x precio. Si
     * no coincide con el total registrado, lo que se declararia ante la
     * DNIT seria otro importe que el que figura en el sistema.
     */
    private function verificarTotal(NotaCreditoVenta $nota, array $items): void
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $item['cantidad'] * $item['precio_unitario'];
        }

        $total = round($total, 2);

        if (abs($total - (float) $nota->ncv_total) > self::TOLERANCIA) {
            throw new EmisionFacturaException(sprintf(
                'La nota de crédito %s no se puede declarar: el detalle suma Gs. %s pero el total '
                . 'registrado es Gs. %s.',
                $nota->ncv_nrodoc,
                number_format($total, 0, ',', '.'),
                number_format((float) $nota->ncv_total, 0, ',', '.')
            ));
        }
    }

    private function esCdc(mixed $cdc): bool
    {
        return is_string($cdc) && preg_match('/^[0-9]{44}$/', $cdc) === 1;
    }
}
<?php

namespace App\Services\Venta;

use App\Models\Compras\MotivoNotaCredito;
use App\Models\Compras\MovimientoStock;
use App\Models\Compras\Stock;
use App\Models\Compras\TipoComprobante;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta\Caja;
use App\Models\Venta\DetNotaCreditoVenta;
use App\Models\Venta\NotaCreditoVenta;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaDetalle;
use Illuminate\Support\Facades\DB;

use App\Jobs\DeclararNotaCreditoElectronica;
use App\Models\Venta\NotaCreditoVentaFe;
use App\Services\Facturacion\NotaCreditoPayloadBuilder;

use App\Models\Configuracion\Timbrado;

/**
 * Emisión de la Nota de Crédito de una factura de venta.
 *
 * Qué hace, en una sola transacción:
 *   1. Bloquea la factura y recalcula el saldo acreditable de cada línea.
 *   2. Toma un número del talonario propio de Nota de Crédito.
 *   3. Graba cabecera (con snapshot fiscal) y detalle.
 *   4. Si el motivo mueve mercadería, reingresa el stock y asienta el kardex.
 *
 * Qué NO hace, y es deliberado:
 *   - No toca ven_estado. La factura es un documento histórico: sigue
 *     emitida o cobrada. Lo que cambia es su SALDO, que VentaCab::saldo()
 *     calcula desde cobro_venta y nota_credito_venta.
 *   - No devuelve dinero. La NC reconoce el crédito; la devolución
 *     efectiva es un hecho de caja aparte.
 *   - No declara nada a la DNIT. Eso es la etapa de NC electrónica.
 *
 * Criterio de IVA: idéntico al de la factura. Los precios son CON IVA
 * INCLUIDO y el IVA es el contenido (10% = x10/110, 5% = x5/105), para
 * que las líneas de factura y de NC se puedan sumar directo.
 */
class NotaCreditoVentaService
{
    /** Tolerancia de redondeo en guaraníes. */
    private const EPSILON = 0.0001;

    public function __construct(
        private NumeracionVentaService $numeracion,
        private NotaCreditoPayloadBuilder $builderFe,
    ) {}

    // =================================================================
    //  Emisión
    // =================================================================

    /**
     * @param array{ncv_venid:int, ncv_mncid:int, ncv_fecha:string, ncv_obs:?string} $datos
     * @param array<int, array{vnd_id:int, cantidad:float, precio?:float}>            $lineas
     *
     * @throws \DomainException ante cualquier regla de negocio incumplida
     */
        
    public function store(array $datos, array $lineas, Usuario $usuario): NotaCreditoVenta
    {
        return DB::transaction(function () use ($datos, $lineas, $usuario) {
            $factura = VentaCab::lockForUpdate()->find($datos['ncv_venid']);

            if (!$factura) {
                throw new \DomainException('La factura indicada no existe.');
            }

            if ($factura->anulada()) {
                throw new \DomainException('La factura está anulada: no admite notas de crédito.');
            }

            $motivo      = MotivoNotaCredito::findOrFail($datos['ncv_mncid']);
            $afectaStock = $motivo->afectaStock();

            // El Request ya lo valida, pero el service no debe depender de
            // quién lo invoque.
            if ($motivo->exigeObservacion() && mb_strlen(trim((string) ($datos['ncv_obs'] ?? ''))) < 5) {
                throw new \DomainException('Este motivo exige una observación que explique el caso.');
            }

            // 1. Líneas calculadas en el servidor.
            [$detalle, $totales] = $this->armarDetalle($factura, $lineas, $motivo);

                       // 2. Validación de pre-vuelo: la NC NACE ELECTRÓNICA.
            //
            // Todo lo que la DNIT exige y este sistema puede saber sin llamar
            // a la API se comprueba ACÁ, antes de tomar un número. Si la NC no
            // puede ser un documento electrónico válido, no se emite y el
            // talonario no pierde un número. Es la diferencia con "emitir y
            // después convertir": no existe una NC que no haya nacido DE.
            $electronica = (bool) config('facturacion.activa');

            if ($electronica) {
                $this->builderFe->validarEmision($factura, $motivo, $datos['ncv_fecha']);
            }

            // 3. Numeración y configuración fiscal propia.
            $sucursal = $this->resolverSucursal($factura);

            ['timbrado' => $timbrado, 'numero' => $numero] =
                $this->numeracion->tomarNumero($sucursal->suc_id, TipoComprobante::CODIGO_NOTA_CREDITO);

            // La fecha del documento tiene que caer dentro de la vigencia del
            // timbrado con el que se numera (la API lo rechaza si no).
            $this->validarFechaEnTimbrado($datos['ncv_fecha'], $timbrado);

            $caja    = $this->resolverCaja($sucursal, $timbrado->tim_puntexp);
            $empresa = $timbrado->empresa;

            if (!$empresa) {
                throw new \DomainException("El timbrado {$timbrado->tim_numero} no tiene una empresa asociada.");
            }

            // null = la mercadería NO reingresa al stock (baja) o el motivo no la mueve.
            $deposito = $this->resolverDestino($afectaStock, $datos['ncv_depid'] ?? null, $sucursal);

            // 4. Cabecera, con el adquirente copiado de la FACTURA.
            $nota = NotaCreditoVenta::create([
                'ncv_venid'       => $factura->ven_id,
                'ncv_timid'       => $timbrado->tim_id,
                'ncv_cajid'       => $caja->caj_id,
                'ncv_cliid'       => $factura->ven_cliid,
                'ncv_usuid'       => $usuario->usu_id,
                'ncv_sucid'       => $sucursal->suc_id,
                'ncv_mncid'       => $motivo->mnc_id,
                'ncv_depid'       => $deposito?->dpo_id,

                'ncv_timbrado'    => $timbrado->tim_numero,
                'ncv_timb_inicio' => $timbrado->tim_fechin->toDateString(),
                'ncv_timb_venc'   => $timbrado->tim_fechfn->toDateString(),
                'ncv_emp_ruc'     => $empresa->emp_ruc,
                'ncv_emp_razon'   => $empresa->emp_razonsocial,
                'ncv_establec'    => $timbrado->tim_establec,
                'ncv_puntoexp'    => $timbrado->tim_puntexp,
                'ncv_numero'      => $numero,
                'ncv_fecha'       => $datos['ncv_fecha'],

                'ncv_razonsocial' => $factura->ven_razonsocial,
                'ncv_ruc'         => $factura->ven_ruc,
                'ncv_ci'          => $factura->ven_ci,

                'ncv_exenta'      => $totales['exenta'],
                'ncv_grav5'       => $totales['grav5'],
                'ncv_grav10'      => $totales['grav10'],
                'ncv_subtotal'    => $totales['subtotal'],
                'ncv_total'       => $totales['total'],
                'ncv_iva5'        => $totales['iva5'],
                'ncv_iva10'       => $totales['iva10'],
                'ncv_totiva'      => $totales['totiva'],

                'ncv_estado'      => NotaCreditoVenta::ESTADO_EMITIDA,
                'ncv_obs'         => $datos['ncv_obs'] ?? null,
            ]);

            // 5. Detalle y, si hay depósito de destino, reingreso.
            foreach ($detalle as $linea) {
                $dnv = DetNotaCreditoVenta::create(['dnv_ncvid' => $nota->ncv_id] + $linea);

                if ($deposito) {
                    $this->reingresarStock($dnv, $deposito, $usuario, $nota->ncv_nrodoc);
                }
            }

            // 6. El registro electrónico se crea en ESTA MISMA transacción.
            //    Si el commit falla, no queda ni la NC ni su declaración; si
            //    el commit sale, la NC ya existe como documento electrónico
            //    PENDIENTE y el barrido la retomaría aunque la cola fallara.
            if ($electronica) {
                NotaCreditoVentaFe::create([
                    'nfe_ncvid'  => $nota->ncv_id,
                    'nfe_estado' => NotaCreditoVentaFe::ESTADO_PENDIENTE,
                ]);

                // Después del commit: la llamada a la API puede tardar 120 s y
                // mantendría bloqueado el timbrado dentro de la transacción.
                DeclararNotaCreditoElectronica::dispatch($nota->ncv_id)->afterCommit();
            }

            return $nota->fresh(['motivo', 'detalle.producto', 'factura', 'fe']);
        });
    }

    /**
     * Destino de la mercadería devuelta.
     *
     * Sin depósito = no reingresa (merma / baja). Es una decisión legítima:
     * un producto defectuoso que se descarta ya salió del stock cuando se
     * vendió, y reingresarlo solo para sacarlo después ensuciaría el kardex.
     * Lo que NO se hace es reingresarlo por defecto al stock vendible.
     */
    private function resolverDestino(bool $afectaStock, ?int $depositoId, Sucursal $sucursal): ?Deposito
    {
        if (!$afectaStock || !$depositoId) {
            return null;
        }

        $deposito = Deposito::where('dpo_id', $depositoId)
            ->where('dpo_sucid', $sucursal->suc_id)
            ->where('dpo_estado', 1)
            ->first();

        if (!$deposito) {
            throw new \DomainException(
                'El depósito de destino no existe, está inactivo o no pertenece a la sucursal emisora.'
            );
        }

        return $deposito;
    }

    private function validarFechaEnTimbrado(string $fecha, Timbrado $timbrado): void
    {
        $desde = $timbrado->tim_fechin->toDateString();
        $hasta = $timbrado->tim_fechfn->toDateString();

        if ($fecha < $desde || $fecha > $hasta) {
            throw new \DomainException(
                "La fecha {$fecha} está fuera de la vigencia del timbrado {$timbrado->tim_numero} "
                . "({$timbrado->tim_fechin->format('d/m/Y')} al {$timbrado->tim_fechfn->format('d/m/Y')})."
            );
        }
    }

    // =================================================================
    //  Armado del detalle
    // =================================================================

    /**
    * @return array{0: array<int, array<string, mixed>>, 1: array<string, float>}
    */
    private function armarDetalle(VentaCab $factura, array $lineas, MotivoNotaCredito $motivo): array
    {
        if (empty($lineas)) {
            throw new \DomainException('La nota de crédito no tiene ítems.');
        }

        $afectaStock = $motivo->afectaStock();
        $saldos      = $this->saldosPorLinea($factura->ven_id);
        $decimales   = DB::table('unidad_medida')->pluck('umd_decimal', 'umd_codigo');
        $detalle     = [];
        $usadas      = [];
        $item        = 0;

        foreach ($lineas as $entrada) {
            $vnd = VentaDetalle::find($entrada['vnd_id']);

            if (!$vnd || $vnd->vnd_venid !== $factura->ven_id) {
                throw new \DomainException('Uno de los ítems no pertenece a la factura indicada.');
            }

            if (isset($usadas[$vnd->vnd_id])) {
                throw new \DomainException("El ítem \"{$vnd->vnd_descripcion}\" está repetido en el detalle.");
            }
            $usadas[$vnd->vnd_id] = true;

            $saldo = $this->saldosDeLinea($vnd, $saldos);

            // Error de precio: afecta a TODAS las unidades vigentes. La
            // cantidad no se negocia, la impone el saldo.
            $cantidad = $motivo->cantidadFija()
                ? $saldo['cantidad']
                : round((float) $entrada['cantidad'], 4);

            if ($cantidad <= 0) {
                throw new \DomainException("La línea \"{$vnd->vnd_descripcion}\" no tiene unidades por acreditar.");
            }

            $admiteDecimales = (bool) ($decimales[$vnd->vnd_umedida] ?? 0);

            if (!$admiteDecimales && abs($cantidad - round($cantidad)) > self::EPSILON) {
                throw new \DomainException(
                    "\"{$vnd->vnd_descripcion}\" se vende por unidad entera: la cantidad no admite decimales."
                );
            }

            if ($cantidad > $saldo['cantidad'] + self::EPSILON) {
                throw new \DomainException(
                    "La cantidad de \"{$vnd->vnd_descripcion}\" supera el saldo acreditable ({$saldo['cantidad']})."
                );
            }

            $tasa = (float) $vnd->vnd_tasa;

            if ($afectaStock) {
                // Devolución: se acredita lo que esas unidades valen HOY para
                // el cliente, la parte proporcional del saldo de importe de la
                // línea. Sin descuentos previos es el precio facturado. Si
                // devuelve todas las que quedan, es el saldo exacto (sin
                // diferencias de redondeo).
                $valorVenta = abs($cantidad - $saldo['cantidad']) < self::EPSILON
                    ? $saldo['importe']
                    : round($saldo['importe'] * $cantidad / $saldo['cantidad'], 0);

                if ($valorVenta < 0.01) {
                    throw new \DomainException(
                        "La línea \"{$vnd->vnd_descripcion}\" no tiene importe acreditable: ya fue acreditada por completo."
                    );
                }

                $precioUnit = round($valorVenta / $cantidad, 4);
            } else {
                $precioUnit = round((float) ($entrada['precio'] ?? 0), 4);

                if ($precioUnit < 0.01) {
                    throw new \DomainException("Falta el importe unitario a acreditar de \"{$vnd->vnd_descripcion}\".");
                }

                if ($precioUnit > (float) $vnd->vnd_precio + 0.01) {
                    throw new \DomainException(
                        "El importe unitario de \"{$vnd->vnd_descripcion}\" supera el precio facturado."
                    );
                }

                $valorVenta = round($precioUnit * $cantidad, 2);

                // Tope real: nunca se acredita más de lo que vale la línea,
                // sumando descuentos y devoluciones previas.
                if ($valorVenta > $saldo['importe'] + 1) {
                    throw new \DomainException(
                        "El importe de \"{$vnd->vnd_descripcion}\" supera lo que queda acreditable de la línea "
                        . "({$saldo['importe']})."
                    );
                }
            }

            // Si acredita la línea completa, se copia el IVA de la factura en
            // lugar de recalcularlo: evita 1 Gs. de diferencia con el documento.
            $iva = abs($valorVenta - (float) $vnd->vnd_valorventa) < 0.01
                ? (float) $vnd->vnd_iva
                : $this->ivaPorDentro($valorVenta, $tasa);

            $detalle[] = [
                'dnv_vndid'       => $vnd->vnd_id,
                'dnv_prdid'       => $vnd->vnd_prdid,
                'dnv_nroitem'     => ++$item,
                'dnv_codigo'      => $vnd->vnd_codigo,
                'dnv_descripcion' => $vnd->vnd_descripcion,
                'dnv_umedida'     => $vnd->vnd_umedida,
                'dnv_cantidad'    => $cantidad,
                'dnv_precio'      => $precioUnit,
                'dnv_tasa'        => $tasa,
                'dnv_valorventa'  => $valorVenta,
                'dnv_iva'         => $iva,
                'dnv_canrei'      => 0,
            ];
        }

        return [$detalle, $this->calcularTotales($detalle)];
    }


    /** @return array<string, float> */
    private function calcularTotales(array $detalle): array
    {
        $acum = ['exenta' => 0.0, 'grav5' => 0.0, 'grav10' => 0.0, 'iva5' => 0.0, 'iva10' => 0.0];

        foreach ($detalle as $linea) {
            $tasa  = (float) $linea['dnv_tasa'];
            $valor = (float) $linea['dnv_valorventa'];
            $iva   = (float) $linea['dnv_iva'];

            if ($tasa == 10) {
                $acum['grav10'] += $valor;
                $acum['iva10']  += $iva;
            } elseif ($tasa == 5) {
                $acum['grav5'] += $valor;
                $acum['iva5']  += $iva;
            } else {
                $acum['exenta'] += $valor;
            }
        }

        $subtotal = round($acum['exenta'] + $acum['grav5'] + $acum['grav10'], 2);

        if ($subtotal <= 0) {
            throw new \DomainException('El importe de la nota de crédito debe ser mayor a cero.');
        }

        return [
            'exenta'   => round($acum['exenta'], 2),
            'grav5'    => round($acum['grav5'], 2),
            'grav10'   => round($acum['grav10'], 2),
            'subtotal' => $subtotal,
            'total'    => $subtotal,          // el IVA ya está incluido
            'iva5'     => round($acum['iva5'], 2),
            'iva10'    => round($acum['iva10'], 2),
            'totiva'   => round($acum['iva5'] + $acum['iva10'], 2),
        ];
    }

    /** IVA CONTENIDO en un importe con IVA incluido. Igual que VentaService. */
    private function ivaPorDentro(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa), 2) : 0.0;
    }

    // =================================================================
    //  Stock
    // =================================================================

    /**
     * Reingreso por devolución del cliente: ENTRADA al depósito.
     *
     * Es la imagen espejo de StockPedidoService::aplicarSalida(). El costo
     * se recalcula con promedio ponderado tomando como costo de entrada el
     * CPP vigente: así la devolución no distorsiona la valuación del saldo.
     * Si el producto no tiene costo vigente (stock en cero) se recupera el
     * costo con el que salió, leyéndolo del kardex de la venta original.
     *
     * El kardex NO se reescribe: la salida original queda tal cual y esta
     * entrada se asienta como un movimiento nuevo, enlazado a dnv_id.
     */
    private function reingresarStock(
        DetNotaCreditoVenta $dnv,
        Deposito $deposito,
        Usuario $usuario,
        string $nroDoc
    ): void {
        if (!$dnv->dnv_prdid) {
            return;  // línea de envío: no hay mercadería que reingresar
        }

        $producto = Producto::find($dnv->dnv_prdid);

        if (!$producto || !$producto->prd_afestk) {
            return;  // servicio o producto que no controla stock
        }

        $cantidad = (float) $dnv->dnv_cantidad;

        $stock = Stock::where('sto_prdid', $dnv->dnv_prdid)
            ->where('sto_depid', $deposito->dpo_id)
            ->lockForUpdate()
            ->first();

        $cantidadPrevia = $stock ? (float) $stock->sto_cantid : 0.0;
        $costoPrevio    = $stock ? (float) $stock->sto_cosprm : 0.0;

        $costoEntrada = $costoPrevio > 0
            ? $costoPrevio
            : $this->costoDeLaSalidaOriginal($dnv, $deposito);

        $nuevaCantidad = round($cantidadPrevia + $cantidad, 4);
        $nuevoCosto    = $nuevaCantidad > 0
            ? round((($cantidadPrevia * $costoPrevio) + ($cantidad * $costoEntrada)) / $nuevaCantidad, 4)
            : 0.0;

        if ($stock) {
            $stock->update([
                'sto_cantid' => $nuevaCantidad,
                'sto_cosprm' => $nuevoCosto,
                'sto_actual' => now(),
            ]);
        } else {
            // El producto puede no tener fila en ESE depósito si se despachó
            // desde otro: la devolución la crea en el depósito de reingreso.
            Stock::create([
                'sto_prdid'  => $dnv->dnv_prdid,
                'sto_depid'  => $deposito->dpo_id,
                'sto_cantid' => $nuevaCantidad,
                'sto_reserv' => 0,
                'sto_cosprm' => $nuevoCosto,
                'sto_actual' => now(),
            ]);
        }

        MovimientoStock::create([
            'mst_dnvid'  => $dnv->dnv_id,
            'mst_usuid'  => $usuario->usu_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => 'E',
            'mst_cantid' => $cantidad,
            'mst_cosuni' => $costoEntrada,
            'mst_salcan' => $nuevaCantidad,
            'mst_salcos' => round($nuevaCantidad * $nuevoCosto, 4),
            'mst_obs'    => mb_substr("Devolución s/NC {$nroDoc}", 0, 45),
            'mst_stprid' => $dnv->dnv_prdid,
            'mst_stdeid' => $deposito->dpo_id,
        ]);

        $dnv->update(['dnv_canrei' => $cantidad]);
    }

    /**
     * Último costo con el que ese producto salió del depósito. Solo se usa
     * cuando el stock quedó en cero y el CPP se perdió: reingresar a costo
     * cero inventaría mercadería gratis en el balance.
     */
    private function costoDeLaSalidaOriginal(DetNotaCreditoVenta $dnv, Deposito $deposito): float
    {
        $costo = MovimientoStock::where('mst_stprid', $dnv->dnv_prdid)
            ->where('mst_stdeid', $deposito->dpo_id)
            ->where('mst_tipo', 'S')
            ->orderByDesc('mst_id')
            ->value('mst_cosuni');

        return (float) ($costo ?? 0);
    }

    // =================================================================
    //  Anulación
    // =================================================================

    /**
     * Un documento fiscal no se borra: se anula. La fila queda con su
     * número consumido y, si había movido stock, se asienta la reversa
     * enlazada al movimiento original por mst_revid.
     *
     * Anulada la NC, su cantidad deja de consumir saldo acreditable:
     * acreditadoPorLinea() solo cuenta las vigentes.
     */
    public function anular(NotaCreditoVenta $nota, Usuario $usuario, ?string $motivo = null): NotaCreditoVenta
    {
        // Un documento electrónico ya declarado NO se anula internamente: el
        // sistema quedaría en desacuerdo con la DNIT. La vía correcta es un
        // evento de cancelación ante el SIFEN (168 h para documentos distintos
        // de la factura, guía 23.6), que no forma parte de este módulo.
        $fe = $nota->fe;

        if ($fe && $fe->bloqueaAnulacion()) {
            throw new \DomainException(
                $fe->estaEnviado()
                    ? "La nota de crédito {$nota->ncv_nrodoc} ya fue aprobada por la DNIT (CDC {$fe->nfe_cdc}): "
                      . 'no se puede anular en el sistema. Corresponde un evento de cancelación ante el SIFEN.'
                    : "La nota de crédito {$nota->ncv_nrodoc} está en proceso de declaración ante la DNIT. "
                      . 'Esperá a que se resuelva antes de anularla.'
            );
        }

        if ($nota->anulada()) {
            throw new \DomainException('Esta nota de crédito ya está anulada.');
        }

        return DB::transaction(function () use ($nota, $usuario, $motivo) {
            $nota->loadMissing(['detalle', 'fe']);

            foreach ($nota->detalle as $dnv) {
                if ((float) $dnv->dnv_canrei > 0) {
                    $this->revertirReingreso($dnv, $nota, $usuario);
                }
            }

            $nota->update([
                'ncv_estado'  => NotaCreditoVenta::ESTADO_ANULADA,
                'ncv_anulado' => now(),
                'ncv_anusid'  => $usuario->usu_id,
                'ncv_motanu'  => $motivo,
            ]);

            return $nota->refresh();
        });
    }

    /** Saca del depósito lo que la NC había reingresado. */
    private function revertirReingreso(DetNotaCreditoVenta $dnv, NotaCreditoVenta $nota, Usuario $usuario): void
    {
        $cantidad = (float) $dnv->dnv_canrei;

        $stock = Stock::where('sto_prdid', $dnv->dnv_prdid)
            ->where('sto_depid', $nota->ncv_depid)
            ->lockForUpdate()
            ->first();

        $disponible = $stock ? (float) $stock->sto_cantid - (float) $stock->sto_reserv : 0.0;

        if ($disponible < $cantidad - self::EPSILON) {
            throw new \DomainException(
                "No se puede anular la nota de crédito: \"{$dnv->dnv_descripcion}\" ya se volvió a vender "
                . "(disponible {$disponible}, se necesitan {$cantidad})."
            );
        }

        $original = MovimientoStock::where('mst_dnvid', $dnv->dnv_id)
            ->where('mst_tipo', 'E')
            ->orderByDesc('mst_id')
            ->first();

        $costo         = (float) $stock->sto_cosprm;
        $nuevaCantidad = round((float) $stock->sto_cantid - $cantidad, 4);

        $stock->update(['sto_cantid' => $nuevaCantidad, 'sto_actual' => now()]);

        MovimientoStock::create([
            'mst_dnvid'  => $dnv->dnv_id,
            'mst_revid'  => $original?->mst_id,
            'mst_usuid'  => $usuario->usu_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => 'S',
            'mst_cantid' => $cantidad,
            'mst_cosuni' => $costo,
            'mst_salcan' => $nuevaCantidad,
            'mst_salcos' => round($nuevaCantidad * $costo, 4),
            'mst_obs'    => mb_substr("Anulación NC {$nota->ncv_nrodoc}", 0, 45),
            'mst_stprid' => $dnv->dnv_prdid,
            'mst_stdeid' => $nota->ncv_depid,
        ]);

        $dnv->update(['dnv_canrei' => 0]);
    }

    // =================================================================
    //  Resolución de configuración
    // =================================================================

    private function resolverSucursal(VentaCab $factura): Sucursal
    {
        $sucursal = Sucursal::find($factura->ven_sucid);

        if (!$sucursal) {
            throw new \DomainException('No se pudo determinar la sucursal emisora de la factura.');
        }

        return $sucursal;
    }

    /**
     * La caja es el punto de expedición y tiene que ser el MISMO que el del
     * talonario de NC, igual que en VentaService::resolverCaja().
     */
    private function resolverCaja(Sucursal $sucursal, string $puntoExpedicion): Caja
    {
        $caja = Caja::where('caj_sucid', $sucursal->suc_id)
            ->where('caj_puntexp', $puntoExpedicion)
            ->where('caj_estado', 1)
            ->first();

        if (!$caja) {
            throw new \DomainException(
                "No hay una caja activa para el punto de expedición {$puntoExpedicion} "
                . "de la sucursal {$sucursal->suc_nombre}."
            );
        }

        return $caja;
    }

       /**
     * Dos saldos por línea, porque un descuento y una devolución no gastan lo mismo:
     *   devuelto   : unidades que ya volvieron (solo NC de motivos que mueven mercadería)
     *   acreditado : importe ya acreditado (TODAS las NC vigentes)
     *
     * Una NC anulada no cuenta: devuelve el saldo que había consumido.
     *
     * @return array<int, array{devuelto: float, acreditado: float}>
     */
    public function saldosPorLinea(int $ventaId): array
    {
        return DB::table('det_nota_credito_venta as d')
            ->join('nota_credito_venta as n', 'n.ncv_id', '=', 'd.dnv_ncvid')
            ->join('motivo_nota_credito as m', 'm.mnc_id', '=', 'n.ncv_mncid')
            ->where('n.ncv_venid', $ventaId)
            ->where('n.ncv_estado', NotaCreditoVenta::ESTADO_EMITIDA)
            ->groupBy('d.dnv_vndid')
            ->selectRaw('d.dnv_vndid as vnd_id,
                         COALESCE(SUM(CASE WHEN m.mnc_afesto = 1 THEN d.dnv_cantidad ELSE 0 END), 0) as devuelto,
                         COALESCE(SUM(d.dnv_valorventa), 0) as acreditado')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (int) $r->vnd_id => ['devuelto' => (float) $r->devuelto, 'acreditado' => (float) $r->acreditado],
            ])
            ->all();
    }

    /**
    * Lo que todavía se puede acreditar de una línea de la factura.
    * Acepta un modelo o una fila de DB::table (solo lee propiedades).
    *
    * 'precio' es el precio NETO VIGENTE por unidad: lo que valen hoy las
    * unidades que quedan, descontando lo ya acreditado. Sin descuentos
    * previos coincide con el precio facturado.
    *
    * @return array{cantidad: float, importe: float, precio: float}
    */
    public function saldosDeLinea(object $vnd, array $saldos): array
    {
        $s = $saldos[(int) $vnd->vnd_id] ?? ['devuelto' => 0.0, 'acreditado' => 0.0];

        $cantidad = round((float) $vnd->vnd_cantidad - $s['devuelto'], 4);
        $importe  = round((float) $vnd->vnd_valorventa - $s['acreditado'], 2);

        return [
            'cantidad' => $cantidad,
            'importe'  => $importe,
            'precio'   => $cantidad > 0 ? round($importe / $cantidad, 4) : 0.0,
        ];
    }
    
}
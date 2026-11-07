<?php

namespace App\Services\Venta;

use App\Models\Pedido\PedidoPago;
use App\Models\Servicio\ServicioPago;
use App\Models\Usuario;
use App\Models\Venta\CobroVenta;
use App\Models\Venta\VentaCab;
use Illuminate\Validation\ValidationException;

/**
 * Registro del cobro de una factura de venta (pedido o servicio técnico).
 *
 * El cobro es un HECHO con fecha, vía y referencia propia, distinto de la
 * intención de pago declarada (pedido_pago / servicio_pago). Un cobro mal
 * cargado no se corrige pisando columnas: se anula y se registra el correcto.
 *
 * DEBE invocarse dentro de la transacción del caller.
 */
class CobroVentaService
{
    private const TOLERANCIA = 0.01;

    /**
     * @param array{monto_verificado: float, nro_transaccion: ?string, banco: ?string,
     *              fecha_pago: ?string, observacion: ?string} $datos
     */
    public function registrar(VentaCab $factura, PedidoPago|ServicioPago $pago, array $datos, Usuario $usuario): CobroVenta
    {
        if (!$factura->esVigente()) {
            throw ValidationException::withMessages([
                'cobro' => "La factura {$factura->ven_nrodoc} está anulada: no se le puede aplicar un cobro.",
            ]);
        }

        $esServicio = $pago instanceof ServicioPago;
        $columna    = $esServicio ? 'cob_spaid' : 'cob_ppaid';
        $pagoId     = $esServicio ? $pago->spa_id : $pago->ppa_id;

        // uq_cob_ppa_activo / uq_cob_spa_activo ya lo impiden; acá con un mensaje claro.
        $existente = CobroVenta::where($columna, $pagoId)->activo()->lockForUpdate()->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'cobro' => 'Este pago ya tiene un cobro registrado. Para corregirlo hay que anular el cobro anterior.',
            ]);
        }

        $cobro = CobroVenta::create([
            'cob_venid'  => $factura->ven_id,
            'cob_mpaid'  => $esServicio ? $pago->spa_mpaid : $pago->ppa_mpaid,
            'cob_monto'  => $datos['monto_verificado'],
            'cob_nrotrx' => $datos['nro_transaccion'] ?? null,
            'cob_banco'  => $datos['banco'] ?? null,
            // Fecha REAL de recepción (guía 29.1). Sin dato, hoy.
            'cob_fecha'  => $datos['fecha_pago'] ?? now()->toDateString(),
            'cob_observ' => $datos['observacion'] ?? null,
            $columna     => $pagoId,
            'cob_usuid'  => $usuario->usu_id,
            'cob_estado' => CobroVenta::ESTADO_ACTIVO,
        ]);

        $this->actualizarEstadoFactura($factura);

        return $cobro;
    }

    /**
     * Emitida -> Cobrada cuando los cobros activos alcanzan el total. En una
     * final con anticipo, ven_total ya es el NETO: el anticipo no se cobra dos veces.
     */
    private function actualizarEstadoFactura(VentaCab $factura): void
    {
        if ($factura->ven_estado !== VentaCab::ESTADO_EMITIDA) {
            return;
        }

        if ($factura->totalCobrado() + self::TOLERANCIA >= (float) $factura->ven_total) {
            $factura->update(['ven_estado' => VentaCab::ESTADO_COBRADA]);
        }
    }

        /**
     * Anula un cobro de SERVICIO TÉCNICO mal cargado. No borra: deja quién,
     * cuándo y por qué. Si la factura estaba Cobrada y deja de estarlo,
     * vuelve a Emitida.
     *
     * Los cobros de pedido no se anulan acá: el cobro que completa un pedido
     * mueve stock y estado del pedido, y su reversa se resuelve aparte.
     *
     * Bloqueos:
     *   - anticipo aplicado en una final: el descuento ya se usó;
     *   - devoluciones registradas contra la factura: ese dinero salió
     *     respaldado por lo cobrado.
     *
     * DEBE invocarse dentro de la transacción del caller.
     */
    public function anular(CobroVenta $cobro, string $motivo, Usuario $usuario): VentaCab
    {
        if ($cobro->cob_spaid === null) {
            throw ValidationException::withMessages([
                'cobro' => 'Solo se pueden anular cobros de servicio técnico.',
            ]);
        }

        if ($cobro->cob_estado !== CobroVenta::ESTADO_ACTIVO) {
            throw ValidationException::withMessages(['cobro' => 'Este cobro ya está anulado.']);
        }

        $factura = VentaCab::whereKey($cobro->cob_venid)->lockForUpdate()->firstOrFail();

        if ($factura->esAnticipo() && $factura->totalAplicado() > self::TOLERANCIA) {
            throw ValidationException::withMessages([
                'cobro' => 'El anticipo ya está aplicado en la factura final: su cobro no se puede anular.',
            ]);
        }

        if ($factura->totalDevuelto() > 0) {
            throw ValidationException::withMessages([
                'cobro' => "La factura {$factura->ven_nrodoc} tiene devoluciones de dinero registradas: anulalas antes.",
            ]);
        }

        $cobro->update([
            'cob_estado' => CobroVenta::ESTADO_ANULADO,
            'cob_fecanu' => now(),
            'cob_anusid' => $usuario->usu_id,
            'cob_motanu' => $motivo,
        ]);

        if ($factura->ven_estado === VentaCab::ESTADO_COBRADA
            && $factura->totalCobrado() + self::TOLERANCIA < (float) $factura->ven_total) {
            $factura->update(['ven_estado' => VentaCab::ESTADO_EMITIDA]);
        }

        return $factura;
    }
}
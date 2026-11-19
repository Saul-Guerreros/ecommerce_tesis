<?php

namespace App\Services\Venta;

use App\Models\Usuario;
use App\Models\Venta\CtaCobrar;
use App\Models\Venta\Recibo;
use App\Models\Venta\ReciboDetalle;
use App\Models\Venta\VentaCab;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobranza de cuotas: genera el RECIBO que se entrega al cliente.
 *
 * Un recibo puede cubrir varias cuotas, pero siempre de UNA factura y
 * siempre consecutivas desde la mas antigua pendiente: no se permite
 * saltear cuotas, porque pagar la 4 dejando viva la 3 hace que la mora
 * y los vencimientos dejen de significar algo.
 *
 * Sin pagos parciales: el monto no se toma del request, se arma sumando
 * las cuotas. Si el admin pudiera escribirlo, cco_saldo y el total del
 * recibo podrian divergir.
 */
class CtaCobrarService
{
    public function __construct(
        private CuotaPagoService $pagos,
        private NumeracionReciboService $numeracion,
    ) {
    }

    /**
     * Cobra una o varias cuotas en un solo recibo.
     *
     * @param int[] $cuotaIds
     * @param array{rec_mpaid: int, rec_fecha: string, rec_nrotrx: ?string,
     *              rec_banco: ?string, rec_observ: ?string} $datos
     */
    public function registrarCobro(array $cuotaIds, array $datos, Usuario $usuario): Recibo
    {
        if (empty($cuotaIds)) {
            throw ValidationException::withMessages([
                'cuotas' => 'Seleccioná al menos una cuota para cobrar.',
            ]);
        }

        return DB::transaction(function () use ($cuotaIds, $datos, $usuario) {
            // lockForUpdate: dos cajeros cobrando las mismas cuotas a la vez
            // no pueden pasar los dos por las validaciones de abajo.
            $cuotas = CtaCobrar::whereIn('cco_id', array_unique($cuotaIds))
                ->orderBy('cco_nrocuo')
                ->lockForUpdate()
                ->get();

            if ($cuotas->count() !== count(array_unique($cuotaIds))) {
                throw ValidationException::withMessages([
                    'cuotas' => 'Alguna de las cuotas seleccionadas no existe.',
                ]);
            }

            $factura = $this->validarCuotas($cuotas);

            $total = round((float) $cuotas->sum('cco_monto'), 2);

            $recibo = Recibo::create([
                'rec_numero' => $this->numeracion->siguiente(),
                'rec_venid'  => $factura->ven_id,
                'rec_mpaid'  => $datos['rec_mpaid'],
                'rec_monto'  => $total,
                'rec_nrotrx' => $datos['rec_nrotrx'] ?? null,
                'rec_banco'  => $datos['rec_banco'] ?? null,
                'rec_fecha'  => $datos['rec_fecha'],
                'rec_observ' => $datos['rec_observ'] ?? null,
                'rec_ppaid'  => null,   // no nace del checkout
                'rec_usuid'  => $usuario->usu_id,
                'rec_estado' => Recibo::ESTADO_ACTIVO,
            ]);

            foreach ($cuotas as $cuota) {
                ReciboDetalle::create([
                    'rdt_recid' => $recibo->rec_id,
                    'rdt_ccoid' => $cuota->cco_id,
                    'rdt_monto' => $cuota->cco_monto,
                ]);

                // Las tres columnas se mueven juntas: ck_cco_coherencia.
                $cuota->update([
                    'cco_estado' => CtaCobrar::ESTADO_COBRADA,
                    'cco_saldo'  => 0,
                    'cco_feccob' => $datos['rec_fecha'],
                ]);

                // Una declaración puede cubrir varias cuotas: se confirma
                // una sola vez, cuando ya no le queda ninguna pendiente.
                $declarado = $cuota->pagoVigente()->first();

                if ($declarado && !$this->leQuedanPendientes($declarado)) {
                    $this->pagos->confirmar($declarado, $usuario);
                }
            }

            $this->actualizarEstadoFactura($factura);

            return $recibo->load('detalle.cuota', 'metodoPago');
        });
    }
        /** ¿A esta declaración le queda alguna cuota sin cobrar? */
    private function leQuedanPendientes(\App\Models\Venta\CuotaPago $pago): bool
    {
        return CtaCobrar::whereIn(
            'cco_id',
            \App\Models\Venta\CuotaPagoDetalle::where('cpd_cpaid', $pago->cpa_id)->select('cpd_ccoid'),
        )
        ->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE)
        ->exists();
    }


    /**
     * Anula un recibo: todas sus cuotas vuelven a Pendiente. El recibo no
     * se borra, queda con rec_estado = 0 y su motivo.
     */
    public function anularRecibo(Recibo $recibo, string $motivo, Usuario $usuario): Recibo
    {
        if (!$recibo->estaActivo()) {
            throw ValidationException::withMessages([
                'recibo' => 'Este recibo ya está anulado.',
            ]);
        }

        if ($recibo->detalle()->count() === 0) {
            throw ValidationException::withMessages([
                'recibo' => 'Este cobro no corresponde a cuotas: se anula desde la gestión del pedido.',
            ]);
        }

        return DB::transaction(function () use ($recibo, $motivo, $usuario) {
            $recibo->update([
                'rec_estado' => Recibo::ESTADO_ANULADO,
                'rec_fecanu' => now(),
                'rec_anusid' => $usuario->usu_id,
                'rec_motanu' => $motivo,
            ]);

            foreach ($recibo->detalle as $linea) {
                $cuota = CtaCobrar::lockForUpdate()->find($linea->rdt_ccoid);

                $cuota?->update([
                    'cco_estado' => CtaCobrar::ESTADO_PENDIENTE,
                    'cco_saldo'  => $cuota->cco_monto,
                    'cco_feccob' => null,
                ]);
            }

            // La factura deja de estar Cobrada: vuelve a tener saldo.
            $factura = $recibo->factura()->lockForUpdate()->first();

            if ($factura && (int) $factura->ven_estado === VentaCab::ESTADO_COBRADA) {
                $factura->update(['ven_estado' => VentaCab::ESTADO_EMITIDA]);
            }

            return $recibo->load('detalle.cuota', 'metodoPago');
        });
    }

    /**
     * Verifica que las cuotas se puedan cobrar juntas y devuelve su
     * factura. Tres reglas: misma factura, todas pendientes, y
     * consecutivas desde la más antigua pendiente.
     */
    private function validarCuotas(Collection $cuotas): VentaCab
    {
        $facturas = $cuotas->pluck('cco_venid')->unique();

        if ($facturas->count() > 1) {
            throw ValidationException::withMessages([
                'cuotas' => 'Un recibo sólo puede cubrir cuotas de una misma factura.',
            ]);
        }

        foreach ($cuotas as $cuota) {
            if ($cuota->cco_estado === CtaCobrar::ESTADO_COBRADA) {
                throw ValidationException::withMessages([
                    'cuotas' => "La cuota N° {$cuota->cco_nrocuo} ya fue cobrada.",
                ]);
            }

            if ($cuota->cco_estado === CtaCobrar::ESTADO_ANULADA) {
                throw ValidationException::withMessages([
                    'cuotas' => "La cuota N° {$cuota->cco_nrocuo} está anulada.",
                ]);
            }
        }

        $factura = VentaCab::lockForUpdate()->find($facturas->first());

        if (!$factura || !$factura->esVigente()) {
            throw ValidationException::withMessages([
                'cuotas' => 'La factura de estas cuotas está anulada.',
            ]);
        }

        $this->validarConsecutivas($factura, $cuotas);

        return $factura;
    }

    /**
     * Las cuotas elegidas tienen que ser las N mas antiguas pendientes.
     * Pagar la 4 dejando viva la 3 haria que la mora y los vencimientos
     * dejen de significar algo.
     */
    private function validarConsecutivas(VentaCab $factura, Collection $cuotas): void
    {
        $pendientes = CtaCobrar::where('cco_venid', $factura->ven_id)
            ->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE)
            ->orderBy('cco_nrocuo')
            ->pluck('cco_nrocuo', 'cco_id');

        $esperadas = $pendientes->take($cuotas->count())->keys()->sort()->values();
        $elegidas  = $cuotas->pluck('cco_id')->sort()->values();

        if ($esperadas->toArray() !== $elegidas->toArray()) {
            $primera = $pendientes->first();

            throw ValidationException::withMessages([
                'cuotas' => "Las cuotas se cobran en orden. La próxima a cobrar es la N° {$primera}.",
            ]);
        }
    }

    /** Emitida -> Cobrada cuando no queda ninguna cuota pendiente. */
    private function actualizarEstadoFactura(VentaCab $factura): void
    {
        if ((int) $factura->ven_estado !== VentaCab::ESTADO_EMITIDA) {
            return;
        }

        $pendientes = CtaCobrar::where('cco_venid', $factura->ven_id)
            ->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE)
            ->count();

        if ($pendientes === 0) {
            $factura->update(['ven_estado' => VentaCab::ESTADO_COBRADA]);
        }
    }
}
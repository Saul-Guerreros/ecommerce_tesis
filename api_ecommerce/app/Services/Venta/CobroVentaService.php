<?php

namespace App\Services\Venta;

use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use App\Models\Venta\Recibo;
use App\Models\Venta\VentaCab;
use Illuminate\Validation\ValidationException;

/**
 * Registro del cobro de una factura de venta.
 *
 * Es el equivalente, del lado de ventas, de lo que PagoCompra resuelve del
 * lado de compras: el cobro es un HECHO con fecha, via y comprobante
 * bancario propio, distinto de la intencion de pago que el cliente declaro
 * en el checkout (pedido_pago).
 *
 * Separarlo tiene una consecuencia concreta: un cobro mal cargado ya no se
 * corrige pisando columnas de pedido_pago, donde el dato viejo se perdia.
 * Se anula el cobro y se registra el correcto, y los dos quedan a la vista.
 *
 * DEBE invocarse dentro de la transaccion del caller: el cobro, el estado
 * del pago y el descuento de stock son un solo hecho economico.
 */
class CobroVentaService
{
    /** Guaranies redondeados: una diferencia menor a un centimo es ruido de calculo. */
    private const TOLERANCIA = 0.01;

    /**
     * @param array{monto_verificado: float, nro_transaccion: ?string, banco: ?string,
     *              fecha_pago: ?string, observacion: ?string} $datos
     */
    public function registrar(VentaCab $factura, PedidoPago $pago, array $datos, Usuario $usuario): Recibo
    {
        if (!$factura->esVigente()) {
            throw ValidationException::withMessages([
                'cobro' => 'La factura de este pedido está anulada: no se le puede aplicar un cobro.',
            ]);
        }

        // uq_rec_ppa_activo ya lo impide en la base. Aca se adelanta para
        // devolver un mensaje entendible en lugar de un error de SQL.
        $existente = Recibo::where('rec_ppaid', $pago->ppa_id)
            ->activo()
            ->lockForUpdate()
            ->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'cobro' => 'Este pago ya tiene un cobro registrado. Para corregirlo hay que anular el cobro anterior.',
            ]);
        }

        $cobro = Recibo::create([
            // rec_numero queda NULL: en una venta al contado el documento
            // del cliente es la factura, no un recibo.
            'rec_venid'  => $factura->ven_id,
            'rec_mpaid'  => $pago->ppa_mpaid,
            'rec_monto'  => $datos['monto_verificado'],
            'rec_nrotrx' => $datos['nro_transaccion'] ?? null,
            'rec_banco'  => $datos['banco'] ?? null,

            // La fecha del cobro es la del extracto bancario. Si el admin no
            // la carga se usa hoy: rec_fecha es NOT NULL y una fecha vacia
            // dejaria el cobro fuera de cualquier arqueo por periodo.
            'rec_fecha'  => $datos['fecha_pago'] ?? now()->toDateString(),

            'rec_observ' => $datos['observacion'] ?? null,
            'rec_ppaid'  => $pago->ppa_id,
            'rec_usuid'  => $usuario->usu_id,
            'rec_estado' => Recibo::ESTADO_ACTIVO,
        ]);

        $this->actualizarEstadoFactura($factura);

        return $cobro;
    }

    /**
     * Emitida -> Cobrada cuando la suma de los cobros activos alcanza el
     * total. Se calcula desde cobro_venta y no se acumula en una columna:
     * un saldo guardado puede quedar desincronizado si un cobro se anula,
     * la suma no.
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
}
<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pantalla final del checkout, después de declarar el pago.
 *
 * El "caso" lo decide el backend y no la pantalla, porque depende de
 * metodo_pago.mpa_requcom (un dato del catálogo, no de la UI):
 *
 *   REVISION       -> todos los pagos llevan comprobante (transferencia
 *                     y/o billetera). "Pago en revisión".
 *   PARCIAL        -> mezcla de comprobante + efectivo. "Pago parcial en
 *                     revisión", con saldo contra entrega.
 *   CONTRAENTREGA  -> sólo efectivo. "Pedido confirmado", monto a abonar.
 *
 * Si mañana se agrega un método nuevo al catálogo, la clasificación sigue
 * funcionando sin tocar esta clase ni el frontend.
 */
class ResultadoPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pedido = $this->resource;
        $pagos  = $pedido->pagos;

        $conComprobante = $pagos->filter(fn ($p) => $p->metodoPago?->mpa_requcom);
        $contraEntrega  = $pagos->filter(fn ($p) => $p->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA);

        return [
            'ped_numero'  => $pedido->ped_numero,
            'ped_total'   => (float) $pedido->ped_total,
            'ped_creado'  => $pedido->ped_creado,
            'caso'        => $this->resolverCaso($conComprobante->count(), $contraEntrega->count()),
            'estado_pago' => $pedido->estadoPago?->epa_codigo,

            // Lista completa: es lo que permite mostrar "primer pago Gs. X,
            // segundo pago Gs. Y" cuando el cliente hizo dos o más
            // transferencias.
            'pagos' => $pagos->values()->map(fn ($p) => [
                'orden'             => (int) $p->ppa_orden,
                'mpa_codigo'        => $p->metodoPago?->mpa_codigo,
                'mpa_descri'        => $p->metodoPago?->mpa_descri,
                'monto'             => (float) $p->ppa_monto,
                'tiene_comprobante' => $p->comprobante !== null,
            ]),

            'monto_en_revision'   => (float) $conComprobante->sum('ppa_monto'),
            'monto_contraentrega' => (float) $contraEntrega->sum('ppa_monto'),

            'entrega' => $this->entrega($pedido),
        ];
    }

    private function resolverCaso(int $conComprobante, int $contraEntrega): string
    {
        return match (true) {
            $conComprobante > 0 && $contraEntrega > 0 => 'PARCIAL',
            $conComprobante > 0                       => 'REVISION',
            default                                   => 'CONTRAENTREGA',
        };
    }

    /**
     * Los casos 2 y 3 de la pantalla final cambian según dónde recibe el
     * cliente: sucursal (con nombre y horario) o su propia dirección.
     */
    private function entrega($pedido): array
    {
        $entrega  = $pedido->entrega;
        $esRetiro = $pedido->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO;

        $base = [
            'modalidad' => $esRetiro ? 'retiro' : 'envio',
            'receptor'  => trim(($entrega?->pen_nombre ?? '') . ' ' . ($entrega?->pen_apelli ?? '')),
        ];

        if ($esRetiro) {
            $sucursal = $entrega?->sucursal;

            return $base + [
                'suc_nombre'    => $sucursal?->suc_nombre,
                'suc_direccion' => $sucursal?->suc_direccion,
                'suc_horario'   => $sucursal?->suc_horario,
            ];
        }

        return $base + [
            'direccion'    => $this->direccionLegible($entrega),
            'ciudad'       => $entrega?->pen_ciudad,
            'departamento' => $entrega?->pen_departa,
            'referencia'   => $entrega?->pen_referencia,
        ];
    }

    /**
     * Una sola línea con la dirección tal como la cargó el cliente. Se arma
     * acá y no en Angular para que la tienda, el panel admin y cualquier
     * comprobante futuro muestren exactamente lo mismo.
     */
    private function direccionLegible($entrega): ?string
    {
        if (!$entrega) {
            return null;
        }

        $calle = trim(($entrega->pen_calle ?? '') . ' ' . ($entrega->pen_nrocasa ?? ''));

        $partes = array_filter([
            $calle !== '' ? $calle : null,
            $entrega->pen_barrio,
            $entrega->pen_ciudad,
        ]);

        return $partes ? implode(' · ', $partes) : null;
    }
}
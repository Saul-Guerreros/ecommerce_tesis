<?php

namespace App\Http\Resources\Pedido;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pantalla final del checkout, después de declarar el pago.
 *
 * El "caso" lo decide el backend y no la pantalla, porque depende de
 * metodo_pago.mpa_requcom (un dato del catálogo, no de la UI):
 *
 *   REVISION       -> hay pagos con comprobante (transferencia y/o
 *                     billetera) y ningún efectivo. "Pago en revisión".
 *   PARCIAL        -> mezcla de comprobante + efectivo. "Pago parcial en
 *                     revisión", con saldo contra entrega.
 *   CONTRAENTREGA  -> sólo efectivo. "Pedido confirmado", monto a abonar.
 *   CHEQUE         -> sólo cheque. "Pendiente de recepción del cheque".
 *
 * El cheque no es una variante de "en revisión": no hay comprobante que
 * mirar, hay un papel que tiene que llegar a la sucursal dentro de un
 * plazo. Por eso sale del conteo de revisión y tiene su propio bloque
 * (`cheque`), que la pantalla muestra además del mensaje del caso. Así un
 * pago dividido entre transferencia y cheque muestra los dos mensajes, cada
 * uno con lo suyo.
 */
class ResultadoPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pedido = $this->resource;
        $pagos  = $pedido->pagos;

        $conCheque     = $pagos->filter(fn ($p) => $p->metodoPago?->mpa_codigo === MetodoPago::CHEQUE);
        $contraEntrega = $pagos->filter(fn ($p) => $p->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA);

        // Comprobante a verificar: el cheque queda afuera a propósito.
        $enRevision = $pagos->filter(
            fn ($p) => $p->metodoPago?->requiereVerificacion()
                && $p->metodoPago?->mpa_codigo !== MetodoPago::CHEQUE
        );

        return [
            'ped_numero'  => $pedido->ped_numero,
            'ped_total'   => (float) $pedido->ped_total,
            'ped_creado'  => $pedido->ped_creado,
            'caso'        => $this->resolverCaso($enRevision->count(), $contraEntrega->count(), $conCheque->count()),
            'estado_pago' => $pedido->estadoPago?->epa_codigo,

            'pagos' => $pagos->values()->map(fn ($p) => [
                'orden'             => (int) $p->ppa_orden,
                'mpa_codigo'        => $p->metodoPago?->mpa_codigo,
                'mpa_descri'        => $p->metodoPago?->mpa_descri,
                'monto'             => (float) $p->ppa_monto,
                'tiene_comprobante' => $p->comprobante !== null,
                'cheque'            => $p->cheque ? [
                    'tipo'        => $p->cheque->pch_tipo,
                    'tipo_descri' => $p->cheque->descripcionTipo(),
                    'banco'       => $p->cheque->pch_banco,
                    'nrodoc'      => $p->cheque->pch_nrodoc,
                    'nombre'      => $p->cheque->pch_nombre,
                ] : null,
            ]),

            'monto_en_revision'   => (float) $enRevision->sum('ppa_monto'),
            'monto_cheque'        => (float) $conCheque->sum('ppa_monto'),
            'monto_contraentrega' => (float) $contraEntrega->sum('ppa_monto'),

            'cheque'  => $this->cheque($pedido, $conCheque),
            'entrega' => $this->entrega($pedido),
        ];
    }

    private function resolverCaso(int $enRevision, int $contraEntrega, int $cheque): string
    {
        return match (true) {
            $enRevision > 0 && $contraEntrega > 0 => 'PARCIAL',
            $enRevision > 0                       => 'REVISION',
            $contraEntrega > 0                    => 'CONTRAENTREGA',
            $cheque > 0                           => 'CHEQUE',
            default                               => 'CONTRAENTREGA',
        };
    }

    /**
     * Lo que el cliente necesita para entregar el cheque: cuánto, a nombre
     * de quién, dónde y hasta cuándo.
     *
     * La sucursal sale SIEMPRE de este bloque y no de `entrega`, porque con
     * envío a domicilio `entrega` trae la dirección del cliente y no la de
     * la tienda, y el cheque igual se entrega en la sucursal.
     *
     * El plazo es el más cercano entre los cheques del pedido. Con pago
     * dividido nacen juntos, así que coinciden; tomar el menor garantiza
     * que el aviso nunca prometa más tiempo del que realmente hay.
     */
    private function cheque($pedido, $conCheque): ?array
    {
        if ($conCheque->isEmpty()) {
            return null;
        }

        $sucursal = $pedido->entrega?->sucursal ?? $pedido->sucursal;

        $vence = $conCheque
            ->map(fn ($p) => $p->cheque?->pch_fevcto)
            ->filter()
            ->sortBy(fn ($fecha) => $fecha->getTimestamp())
            ->first();

        return [
            'titular' => ConfiguracionTienda::actual()->ctn_pago_cheque_titular ?: null,
            'monto'   => (float) $conCheque->sum('ppa_monto'),
            'cheques' => $conCheque->values()->map(fn ($p) => [
                'orden' => (int) $p->ppa_orden,
                'monto' => (float) $p->ppa_monto,
            ]),

            'vence'       => $vence?->toIso8601String(),
            'vence_label' => $vence?->format('d/m H:i'),
            'horas_plazo' => PagoCheque::HORAS_PLAZO,

            'sucursal' => [
                'nombre'    => $sucursal?->suc_nombre,
                'direccion' => $sucursal?->suc_direccion,
                'horario'   => $sucursal?->suc_horario,
            ],
        ];
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
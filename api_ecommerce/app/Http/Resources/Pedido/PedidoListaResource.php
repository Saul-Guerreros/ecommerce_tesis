<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de la bandeja de pedidos.
 *
 * Deliberadamente NO devuelve colores ni clases CSS: eso es presentacion y
 * vive en el componente Angular. El Resource entrega el codigo del estado
 * (dato) y su descripcion (texto); el panel decide como pintarlo.
 *
 * Tampoco devuelve el detalle del pedido (productos, direccion, pagos uno
 * por uno): esta pantalla es un listado y cargar eso multiplicaria el peso
 * de la respuesta sin que se muestre.
 */
class PedidoListaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pagos = $this->relationLoaded('pagos') ? $this->pagos : collect();

        $cantidadPagos = $pagos->count();
        $confirmados   = $pagos->filter(fn ($p) => $p->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO)->count();
        $rechazados    = $pagos->filter(fn ($p) => $p->estadoPago?->epa_codigo === EstadoPago::RECHAZADO)->count();
        $comprobantes  = $pagos->filter(fn ($p) => $p->comprobante !== null)->count();

        return [
            'ped_id' => $this->ped_id,

            // El numero es unico y permanente: se genera una sola vez al
            // confirmar el pedido y no cambia con el estado.
            'numero' => $this->ped_numero,

            // Dos formatos: el corto para la tabla, el completo para el title.
            'fecha'          => $this->ped_creado?->format('d/m H:i'),
            'fecha_completa' => $this->ped_creado?->format('d/m/Y H:i'),

            'cliente' => [
                'nombre' => $this->nombreCliente(),
                'cedula' => $this->cliente?->persona?->per_cedula,
            ],

            'entrega' => [
                'codigo'   => $this->tipoEntrega?->ten_codigo,
                'etiqueta' => $this->etiquetaEntrega(),
            ],

            // Importe almacenado en el pedido, nunca recalculado contra el
            // catalogo actual. float y no string para que Angular lo formatee.
            'total' => (float) $this->ped_total,

            'metodo_pago' => [
                'etiqueta'       => $this->etiquetaMetodo($cantidadPagos),
                'dividido'       => (bool) $this->ped_pagdiv,
                'cantidad_pagos' => $cantidadPagos,
            ],

            'verificacion_pago' => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->estadoPago?->epa_descri,
                'detalle'     => $this->detalleVerificacion(
                    $cantidadPagos,
                    $confirmados,
                    $rechazados,
                    $comprobantes
                ),
            ],

            'estado_pedido' => [
                'codigo'      => $this->estadoPedido?->est_codigo,
                'descripcion' => $this->estadoPedido?->est_descri,
            ],
        ];
    }

    // ---------- Helpers de presentacion textual ----------

    private function nombreCliente(): string
    {
        $persona = $this->cliente?->persona;

        if (!$persona) {
            return 'Sin cliente asociado';
        }

        $partes = array_filter([
            $persona->per_nombr1,
            $persona->per_apell1,
        ]);

        return $partes ? implode(' ', $partes) : 'Sin nombre registrado';
    }

    /**
     * Etiqueta unificada. ten_descri viene en mayusculas desde el seeder
     * ("RETIRO EN SUCURSAL"), por eso se mapea en vez de mostrarlo crudo:
     * la instruccion pide no alternar "Retiro" y "Retiro en Sucursal"
     * entre registros.
     */
    private function etiquetaEntrega(): string
    {
        return match ($this->tipoEntrega?->ten_codigo) {
            TipoEntrega::RETIRO => 'Retiro en Sucursal',
            TipoEntrega::ENVIO  => 'Envío a domicilio',
            default             => $this->tipoEntrega?->ten_descri ?? '—',
        };
    }

    /**
     * Con pago dividido ped_mpaid es NULL por diseno, asi que la etiqueta
     * se arma desde la cantidad de pagos. No se deduce nunca del estado
     * del pedido.
     */
    private function etiquetaMetodo(int $cantidadPagos): string
    {
        if ($this->ped_pagdiv && $cantidadPagos > 1) {
            return "Dividido {$cantidadPagos} pagos";
        }

        return $this->metodoPago?->mpa_descri
            ?? $this->pagos->first()?->metodoPago?->mpa_descri
            ?? '—';
    }

    /**
     * Linea secundaria bajo el badge de verificacion.
     * La existencia de un comprobante NO implica pago confirmado: por eso
     * "1 comprob." y "Confirmado" son informaciones separadas.
     */
    private function detalleVerificacion(
        int $total,
        int $confirmados,
        int $rechazados,
        int $comprobantes
    ): ?string {
        $partes = [];

        if ($total > 1) {
            $partes[] = "{$confirmados}/{$total} verificados";
        } elseif ($comprobantes > 0) {
            $partes[] = $comprobantes . ' comprob.';
        }

        if ($rechazados > 0 && $confirmados < $total) {
            $partes[] = 'Esperando nuevo';
        }

        return $partes ? implode(' · ', $partes) : null;
    }
}
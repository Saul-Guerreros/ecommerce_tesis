<?php

namespace App\Services\Pedido;

use App\Models\Carrito\Carrito;
use App\Models\Pedido\PedidoTemporal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Unico lugar donde se calcula el total del checkout.
 *
 * Antes el subtotal se calculaba en CheckoutFacturacionService y otra vez
 * (con un filtro distinto) en PedidoService. Ahora que el pago dividido
 * tiene que validar "la suma de los pagos es exactamente el total", un
 * total que cambia segun quien lo calcule es un error de seguridad, no
 * solo una duplicacion.
 *
 * Diferencia importante respecto del calculo anterior de facturacion: aca
 * se filtra la modalidad contado. El checkout "Finalizar Compra" no debe
 * incluir los items a credito, que tienen su propio flujo.
 */
class CheckoutResumenService
{
    private const CON_CARRITO = [
        'producto.publicacionWeb.imagenes',
        'producto.publicacionWeb.categoria',
        'producto.stock',
        'producto.tipoIva',
    ];

    /**
     * Items al contado del usuario. Lanza 422 si no hay ninguno.
     */
    public function items(Usuario $usuario): Collection
    {
        $items = $this->itemsOpcionales($usuario);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'carrito' => 'No tenés productos al contado en tu carrito.',
            ]);
        }

        return $items;
    }

    /**
     * Misma lectura, sin lanzar excepcion: para las pantallas que solo
     * muestran el resumen.
     */
    public function itemsOpcionales(Usuario $usuario): Collection
    {
        return Carrito::where('car_idusu', $usuario->usu_id)
            ->where(function ($q) {
                $q->whereNull('car_modpag')->orWhere('car_modpag', 'contado');
            })
            ->with(self::CON_CARRITO)
            ->get();
    }

    /**
     * Resumen para mostrar en pantalla: con el carrito vacio devuelve ceros.
     *
     * @param  array|null  $entrega  Borrador ptm_entrega; aporta el costo de envio.
     */
    public function resumen(Usuario $usuario, ?array $entrega = null): array
    {
        return $this->resumenDeItems($this->itemsOpcionales($usuario), $entrega);
    }

    /**
     * Variante para quien ya tiene los items cargados y no quiere repetir
     * la consulta (PedidoService::confirmar).
     */
    public function resumenDeItems(Collection $items, ?array $entrega = null): array
    {
        $subtotal = (float) $items->sum('car_total');

        $impuestos = (float) $items->sum(
            fn ($i) => $i->producto ? $i->producto->ivaIncluidoEn((float) $i->car_total) : 0.0
        );

        $costoEnvio = (float) ($entrega['costo_envio'] ?? 0);

        return [
            'subtotal'    => $subtotal,
            'impuestos'   => $impuestos,
            'costo_envio' => $costoEnvio,
            'total'       => $subtotal + $costoEnvio,
        ];
    }

    /**
     * Total exigible al cliente, redondeado a guaranies enteros.
     *
     * Es el valor contra el que se compara la suma de los pagos divididos.
     * Se redondea porque el guarani no tiene centimos: si el total quedara
     * en 99.999,50 el cliente no podria cerrar nunca el pendiente en 0.
     */
    public function totalExigible(Usuario $usuario, ?array $entrega = null): int
    {
        return (int) round($this->resumenDeItems($this->items($usuario), $entrega)['total']);
    }

    /**
     * Costo de envio guardado en el borrador del Paso 1.
     */
    public function entregaDelBorrador(Usuario $usuario): ?array
    {
        return PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first()?->ptm_entrega;
    }
}
<?php

namespace App\Services\Servicio;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\TipoEntrega;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Validation\ValidationException;

/**
 * ¿Se acepta cheque en ESTA solicitud? Misma regla que el pedido
 * (PagoPedidoService::admiteCheque): flags de Datos de Pago según la
 * modalidad de la solicitud (sse_tenid):
 *
 *   RETIRO  el cliente lleva y retira el equipo en la sucursal.
 *   ENVIO   el taller retira y entrega el equipo en el domicilio.
 *
 * El flag decide SI se ofrece el cheque, no DÓNDE se recibe: igual que en
 * pedidos, el papel se entrega siempre en la sucursal dentro de 48 h.
 */
class PoliticaChequeServicio
{
    public function admite(SolicitudServicio $s): bool
    {
        $config = ConfiguracionTienda::actual();

        return $config->tiposChequeHabilitados() !== []
            && $config->aceptaChequeEn($this->modalidad($s));
    }

    /** Texto sin mayúscula inicial: la pantalla lo antepone con "No disponible: ". */
    public function motivo(SolicitudServicio $s): ?string
    {
        if ($this->admite($s)) {
            return null;
        }

        if (ConfiguracionTienda::actual()->tiposChequeHabilitados() === []) {
            return 'la tienda no tiene habilitado el pago con cheque en este momento.';
        }

        return $this->modalidad($s) === TipoEntrega::RETIRO
            ? 'tu servicio es con retiro en sucursal. El pago con cheque solo se acepta en servicios con retiro y entrega a domicilio.'
            : 'tu servicio es con retiro y entrega a domicilio. El pago con cheque solo se acepta en servicios que se llevan y retiran en la sucursal.';
    }

    /** Guard de escritura: si algún medio es CHEQUE, la solicitud tiene que admitirlo. */
    public function exigir(SolicitudServicio $s, iterable $metodos): void
    {
        foreach ($metodos as $metodo) {
            if ($metodo instanceof MetodoPago && $metodo->mpa_codigo === MetodoPago::CHEQUE && !$this->admite($s)) {
                throw ValidationException::withMessages([
                    'mpa_codigo' => 'El pago con cheque no está disponible para este servicio: ' . $this->motivo($s),
                ]);
            }
        }
    }

    /** Las solicitudes de mostrador nacen con RETIRO; sin dato, se asume RETIRO. */
    private function modalidad(SolicitudServicio $s): string
    {
        $s->loadMissing('tipoEntrega');

        return $s->tipoEntrega?->ten_codigo ?? TipoEntrega::RETIRO;
    }
}
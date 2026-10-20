<?php

namespace App\Services\Venta;

use App\Exceptions\EmisionFacturaException;
use App\Models\Compras\TipoComprobante;
use App\Models\Configuracion\Timbrado;

/**
 * Punto UNICO donde se toma un numero de factura.
 *
 * No es un "+1". Es el unico lugar del sistema autorizado a tocar
 * timbrado.tim_nroact, y existe para que nadie numere una factura por su
 * cuenta desde otro lado del codigo.
 *
 * El problema que resuelve el lock:
 *
 *      Admin A                      Admin B
 *   1  lee tim_nroact = 150
 *   2                               lee tim_nroact = 150
 *   3  emite factura 151
 *   4                               emite factura 151
 *
 * Dos facturas con el mismo numero. En Paraguay es una infraccion fiscal
 * y no se puede arreglar despues: no se renumera un comprobante ya
 * entregado al cliente. Con lockForUpdate(), B se queda esperando en el
 * paso 2 hasta que A termina su transaccion, y lee 151.
 *
 * Es el mismo mecanismo que PagoVerificacionService::bloquearPago() usa
 * sobre la fila del pago: dos procesos tocando el mismo registro.
 */
class NumeracionVentaService
{
    /**
     * Toma el siguiente numero del talonario activo de la sucursal y lo
     * marca como consumido.
     *
     * DEBE invocarse dentro de una transaccion ya abierta: el lock vive
     * hasta el commit. Si se llamara suelto, el bloqueo se liberaria al
     * instante y no serviria de nada.
     *
     * @return array{timbrado: Timbrado, numero: int}
     *
     * @throws EmisionFacturaException si no hay talonario utilizable
     */
    public function tomarNumero(int $sucursalId): array
    {
        $tcoId = TipoComprobante::idFactura();

        if (!$tcoId) {
            throw new EmisionFacturaException(
                'No hay un tipo de comprobante "Factura" activo en el sistema. '
                . 'Revisá Configuración → Tipos de comprobante.'
            );
        }

        // uq_tim_activo_punto ya garantiza un solo timbrado activo por
        // (sucursal, tipo, establecimiento, punto de expedicion). El
        // orderBy fija un criterio estable para el caso de una sucursal
        // con mas de un punto habilitado.
        $timbrado = Timbrado::where('tim_sucid', $sucursalId)
            ->where('tim_tcoid', $tcoId)
            ->where('tim_estado', 1)
            ->orderBy('tim_puntexp')
            ->lockForUpdate()
            ->first();

        if (!$timbrado) {
            throw new EmisionFacturaException(
                'La sucursal no tiene un timbrado de Factura activo. '
                . 'Cargalo en Configuración → Timbrados antes de confirmar pagos.'
            );
        }

        $this->validarVigencia($timbrado);
        $this->validarRango($timbrado);

        // tim_nroact arranca en tim_nroini - 1 (lo garantiza ck_tim_nroact),
        // asi que el primer numero emitido es exactamente tim_nroini.
        $numero = $timbrado->tim_nroact + 1;

        $timbrado->update(['tim_nroact' => $numero]);

        return ['timbrado' => $timbrado, 'numero' => $numero];
    }

    private function validarVigencia(Timbrado $timbrado): void
    {
        $hoy = now()->toDateString();

        if ($timbrado->tim_fechin->toDateString() > $hoy) {
            throw new EmisionFacturaException(
                "El timbrado {$timbrado->tim_numero} todavía no entró en vigencia "
                . "(inicia el {$timbrado->tim_fechin->format('d/m/Y')})."
            );
        }

        if ($timbrado->tim_fechfn->toDateString() < $hoy) {
            throw new EmisionFacturaException(
                "El timbrado {$timbrado->tim_numero} venció el "
                . "{$timbrado->tim_fechfn->format('d/m/Y')}. Cargá uno nuevo antes de seguir facturando."
            );
        }
    }

    private function validarRango(Timbrado $timbrado): void
    {
        if ($timbrado->tim_nroact >= $timbrado->tim_nrofin) {
            throw new EmisionFacturaException(
                "El timbrado {$timbrado->tim_numero} agotó su rango de numeración "
                . "(último habilitado: {$timbrado->tim_nrofin}). Cargá un talonario nuevo."
            );
        }
    }
}
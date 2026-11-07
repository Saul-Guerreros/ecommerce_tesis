<?php

namespace App\Http\Requests\Servicio;

/**
 * Factura final. Con medios: el cajero declara el saldo exacto que paga el
 * cliente en el local. Sin medios: se usan los pagos web ya validados.
 */
class GenerarFacturaServicioRequest extends MediosPagoServicioRequest
{
}
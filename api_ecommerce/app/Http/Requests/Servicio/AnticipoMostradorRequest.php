<?php

namespace App\Http\Requests\Servicio;

/** Anticipo presencial: medios obligatorios; el total es la suma de los montos. */
class AnticipoMostradorRequest extends MediosPagoServicioRequest
{
    protected bool $mediosObligatorios = true;
}
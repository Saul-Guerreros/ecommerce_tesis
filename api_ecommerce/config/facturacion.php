<?php

return [
    // Interruptor general. Apagado, el sistema factura internamente como siempre
    // y no encola nada hacia la API. Se enciende con FACTURACION_ACTIVA=true.
    'activa'  => (bool) env('FACTURACION_ACTIVA', false),

    'url'     => env('FACTURACION_URL'),
    'token'   => env('FACTURACION_TOKEN'),
    'llave'   => env('FACTURACION_LLAVE'),
    'timeout' => (int) env('FACTURACION_TIMEOUT', 120),

    // Pide a la API que le mande la factura al cliente (solo a quien eligio CORREO).
    'enviar_correo' => (bool) env('FACTURACION_ENVIAR_CORREO', false),
];
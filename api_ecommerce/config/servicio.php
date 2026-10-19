<?php

/**
 * Parámetros del Módulo 5 - Servicio Técnico.
 * Se centralizan acá para no dejar "números mágicos" en los Services.
 */
return [
    // Vigencia de la garantía de una COMPRA, contada desde la fecha del pedido.
    'garantia_compra_dias' => (int) env('SERVICIO_GARANTIA_COMPRA_DIAS', 365),

    // Fotos por solicitud
    'max_fotos_cliente'     => 5,
    'max_fotos_ingreso'     => 8,
    'max_fotos_diagnostico' => 8,
    'foto_max_kb'           => 5120,

    // Carpeta del disco 'public' (storage/app/public/servicios/{sse_id})
    'carpeta_adjuntos' => 'servicios',

    // Código del trabajo que se cobra cuando el equipo no es reparable.
    'trabajo_revision' => 'REVISION',

    // Paso 6 - Notificaciones al cliente
    'notificar_por_correo' => (bool) env('SERVICIO_NOTIFICAR', true),
    'url_tienda'           => env('URL_TIENDA', 'https://zinformatica.columbiatcc.online'),

    // Panel general (A3): presupuestos que vencen dentro de estos días se destacan
    'alerta_vencimiento_dias' => 2,
];

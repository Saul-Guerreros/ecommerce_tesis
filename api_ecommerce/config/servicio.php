<?php

/**
 * Parámetros del Módulo 5 - Servicio Técnico.
 * Se centralizan acá para no dejar "números mágicos" en los Services.
 */
return [
    // Plazo GENERAL de garantía de una COMPRA, en días, contado desde la ENTREGA del pedido.
    // Es el valor por defecto: se aplica a los productos que no definen su propio
    // plazo (producto.prd_gardia NULL). Al vender se copia a detalle_pedido.dpd_gardia.
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

    // Paso 2: presupuesto ENVIADO sin contacto con el cliente en estos días (tarjeta del panel A3).
    'contacto_alerta_dias' => (int) env('SERVICIO_CONTACTO_ALERTA_DIAS', 2),

    // Diagnóstico preliminar con IA (Gemini). false = siempre responde "no disponible".
    'diagnostico_ia' => (bool) env('SERVICIO_DIAGNOSTICO_IA', true),

    // Punto 7 - Clasificación automática de solicitudes (IA). false = quedan NO_DISPONIBLE.
    'clasificacion_ia' => (bool) env('SERVICIO_CLASIFICACION_IA', true),
];

<?php

/**
 * Parámetros del motor de recomendación (ajustables sin tocar código).
 */
return [
    // Pesos del feedback implícito
    'peso_compra' => 3.0,
    'peso_vista'  => 1.0,

    // Ventanas de entrenamiento
    'dias_compras' => 365,
    'dias_vistas'  => 180,

    // Perfil del visitante
    'dias_perfil'     => 90,
    'vida_media_dias' => 30,   // una interacción de hace 30 días vale la mitad

    // Filtrado colaborativo
    'max_items_usuario' => 50, // tope por usuario (costo O(n²) y ruido)
    'vecinos'           => 20, // vecinos guardados por producto
    'soporte_minimo'    => 2,  // usuarios en común mínimos (en desarrollo podés usar 1)

    // Mezcla híbrida y tendencias
    'peso_tendencia'       => 0.2, // α
    'dias_tendencia'       => 30,
    'peso_vista_tendencia' => 0.1, // 10 vistas ≈ 1 comprador
];
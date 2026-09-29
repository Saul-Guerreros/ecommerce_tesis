/**
 * Datos que el Detalle del Producto PREPARA al presionar cada botón.
 *
 * Son el contrato para las etapas siguientes (carrito y solicitud de
 * crédito). Los importes son informativos: el backend siempre los
 * recalcula (CarritoController::resolverFinanciacion) y nunca confía en
 * un precio enviado por el navegador.
 *
 * `producto_id` solo NO identifica una línea del carrito: la modalidad y,
 * en crédito, `numero_cuotas` forman parte de la identidad.
 */
export interface LineaContado {
  producto_id: number;
  cantidad: number;
  modalidad: 'contado';
  precio_unitario: number;
}

export interface SolicitudCredito {
  producto_id: number;
  cantidad: number;
  modalidad: 'credito';
  numero_cuotas: number;
  porcentaje_recargo: number;
  /** Precio unitario ya con recargo (lo calcula el backend). */
  precio_unitario_financiado: number;
  /** Cuota POR UNIDAD (lo calcula el backend). Equivale a car_montocuo. */
  monto_cuota: number;
  /** Cuota por la CANTIDAD elegida: lo que el cliente vio en el selector. */
  monto_cuota_total: number;
}
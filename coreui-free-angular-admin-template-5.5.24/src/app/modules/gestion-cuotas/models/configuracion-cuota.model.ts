export interface ProductoCuota {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  /** null = el producto todavía no tiene precio de venta. */
  precio_contado: number | null;
  solo_mayorista: boolean;
  cantidad_minima: number;
  cuotas_total: number;
  cuotas_activas: number;
}

/** precio_financiado y monto_cuota los calcula el backend; no se guardan. */
export interface ConfiguracionCuota {
  cfc_id: number;
  cfc_prdid: number;
  cfc_cantidad: number;
  cfc_recargo_pct: number;
  cfc_estado: boolean;
  precio_contado: number;
  precio_financiado: number;
  monto_cuota: number;
}

export interface GuardarCuotaPayload {
  cfc_prdid: number;
  cfc_cantidad: number;
  cfc_recargo_pct: number;
  cfc_estado: boolean;
}
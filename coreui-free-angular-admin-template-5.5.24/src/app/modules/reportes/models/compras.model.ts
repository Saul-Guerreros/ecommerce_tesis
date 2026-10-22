/** Respuestas de /api/admin/reportes/compras/* */

export type VistaCompras = 'proveedores' | 'cxp-vencidas' | 'cxp-por-vencer';

export interface FiltrosComprasExtra {
  proveedor_id?: number | null;
  dias?: number;
}

// ---------- por proveedor ----------
export interface CompraProveedor {
  id: number;
  proveedor: string;
  ruc: string;
  compras: number;
  contado: number;
  credito: number;
  iva: number;
  total: number;
  promedio: number;
  ultima_compra: string;
  participacion: number | null;
}
export interface TotalesComprasProveedor {
  proveedores: number;
  compras: number;
  contado: number;
  credito: number;
  iva: number;
  total: number;
}

// ---------- cuentas por pagar ----------
export interface CuotaPendiente {
  id: number;
  proveedor: string;
  ruc: string;
  factura: string;
  emision: string;
  cuota: string;
  vencimiento: string;
  importe: number;
  pagado: number;
  saldo: number;
  tramo: string;
  dias_vencido?: number;
  dias_para_vencer?: number;
}
export interface TramoDeuda {
  tramo: string;
  cuotas: number;
  saldo: number;
  participacion: number | null;
}
export interface TotalesCuentasPagar {
  cuotas: number;
  proveedores: number;
  importe: number;
  pagado: number;
  saldo: number;
  tramos: TramoDeuda[];
  dias_umbral?: number;
}

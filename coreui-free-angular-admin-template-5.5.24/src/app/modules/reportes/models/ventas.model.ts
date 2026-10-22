/** Respuestas de /api/admin/reportes/ventas/* */

export type VistaVentas = 'periodo' | 'productos' | 'agrupado' | 'metodos-pago' | 'ubicacion';

export interface FiltrosVentasExtra {
  categoria_id?: number | null;
  marca_id?: number | null;
  por?: 'categoria' | 'marca';
}

/** Métricas de venta y margen (venta neta = sin IVA). */
export interface MetricasMargen {
  unidades: number;
  total: number;
  venta_neta: number;
  costo: number;
  margen: number;
  margen_pct: number | null;
}

export interface VentaPeriodo {
  periodo: string;
  etiqueta: string;
  facturas: number;
  total: number;
  ticket: number;
}
export interface TotalesPeriodoVentas {
  total: number;
  facturas: number;
  ticket: number;
}

export interface VentaProducto extends MetricasMargen {
  id: number;
  codigo: string;
  producto: string;
  categoria: string;
  marca: string;
}

export interface VentaAgrupada extends MetricasMargen {
  id: number | null;
  nombre: string;
  participacion: number | null;
}

export interface VentaMetodo {
  metodo: string;
  operaciones: number;
  total: number;
  participacion: number | null;
}
export interface TotalesMetodo {
  total: number;
  operaciones: number;
}

export interface VentaCiudad {
  departamento: string;
  ciudad: string;
  pedidos: number;
  total: number;
  participacion: number | null;
}
export interface TotalesCiudad {
  total: number;
  pedidos: number;
}
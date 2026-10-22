/** Respuestas de /api/admin/reportes/clientes/* */

export type VistaClientes = 'top' | 'nuevos' | 'inactivos';

export interface FiltrosClientesExtra {
  tipo_cliente_id?: number | null;
  limite?: number;
  dias?: number;
}

// ---------- top ----------
export interface ClienteTop {
  id: number;
  posicion: number;
  cliente: string;
  documento: string;
  tipo: string;
  compras: number;
  total: number;
  ticket: number;
  ultima_compra: string;
  participacion: number | null;
}
export interface TotalesTopClientes {
  clientes: number;
  compras: number;
  total: number;
  clientes_activos: number;
  total_periodo: number;
  concentracion: number | null;
}

// ---------- nuevos ----------
export interface ClientesNuevosPeriodo {
  periodo: string;
  etiqueta: string;
  registrados: number;
  nuevos: number;
  recurrentes: number;
  activos: number;
}
export interface TotalesClientesNuevos {
  registrados: number;
  nuevos: number;
  recurrentes: number;
  activos: number;
}

// ---------- inactivos ----------
export interface ClienteInactivo {
  id: number;
  cliente: string;
  documento: string;
  tipo: string;
  email: string | null;
  telefono: string | null;
  ultima_compra: string;
  dias: number;
  tramo: string;
  compras: number;
  total: number;
  ticket: number;
}
export interface TotalesClientesInactivos {
  clientes: number;
  compras: number;
  total: number;
  con_email: number;
  con_telefono: number;
  dias_umbral: number;
  tramos: { tramo: string; clientes: number; total: number }[];
}

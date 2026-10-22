/** Respuestas de /api/admin/metricas/dashboard/* */

// ---------- resumen ----------
export interface PuntoDiario {
  fecha: string;
  total: number;
}

export interface DashboardResumen {
  ventas: {
    total: number;
    anterior: number;
    variacion: number | null;
    facturas: number;
    diario: PuntoDiario[];
  };
  pedidos: { total: number; pendientes: number };
  ticket: { promedio: number; anterior: number; variacion: number | null };
  servicio: { abiertas: number; nuevas: number };
}

// ---------- ventas-compras ----------
export interface PuntoVentasCompras {
  periodo: string;
  etiqueta: string;
  ventas: number;
  compras: number;
}

export interface TotalesPeriodo {
  ventas: number;
  compras: number;
  margen_bruto: number;
  margen_pct: number | null;
  clientes_nuevos: number;
  unidades: number;
}

export interface DashboardVentasCompras {
  serie: PuntoVentasCompras[];
  totales: TotalesPeriodo;
}

// ---------- distribucion ----------
export interface VentaDiaSemana {
  dia: number;
  etiqueta: string;
  nuevos: number;
  recurrentes: number;
}

export interface VentaCategoria {
  id: number | null;
  categoria: string;
  total: number;
  unidades: number;
}

export interface VentaMetodoPago {
  metodo: string;
  total: number;
  operaciones: number;
}

export interface VentaUbicacion {
  departamento: string;
  ciudad?: string;
  pedidos: number;
  total: number;
}

export interface DashboardDistribucion {
  dia_semana: VentaDiaSemana[];
  categorias: VentaCategoria[];
  metodos_pago: VentaMetodoPago[];
  departamentos: VentaUbicacion[];
  ciudades: VentaUbicacion[];
}

// ---------- tablas ----------
export interface EstadoCodigo {
  codigo: string | null;
  descripcion: string | null;
}

export interface PedidoReciente {
  id: number;
  numero: string | null;
  cliente: string;
  total: number;
  fecha: string;
  estado: EstadoCodigo;
  estado_pago: EstadoCodigo;
}

export interface StockBajo {
  id: number;
  codigo: string;
  producto: string;
  deposito: string;
  disponible: number;
  minimo: number;
  faltante: number;
}

export interface DashboardTablas {
  ultimos_pedidos: PedidoReciente[];
  stock_bajo: { total: number; items: StockBajo[] };
}
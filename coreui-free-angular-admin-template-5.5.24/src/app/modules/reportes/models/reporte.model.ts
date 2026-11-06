/** Tipos comunes del Módulo de Métricas y Reportes. */

export type Agrupacion = 'dia' | 'mes' | 'anio';

export type EstadoCarga = 'cargando' | 'ok' | 'vacio' | 'error';

/** Filtros que acepta FiltroReporteRequest en el backend. */
export interface FiltrosReporte {
  desde?: string;          // AAAA-MM-DD
  hasta?: string;          // AAAA-MM-DD
  sucursal_id?: number | null;
  deposito_id?: number | null;
  agrupacion?: Agrupacion;
  limite?: number;
}

export interface OpcionSucursal {
  id: number;
  nombre: string;
  principal: boolean;
}

export interface OpcionDeposito {
  id: number;
  nombre: string;
  sucursal_id: number;
}

export interface OpcionAgrupacion {
  valor: Agrupacion;
  etiqueta: string;
}

export interface ReporteConfig {
  sucursales: OpcionSucursal[];
  depositos: OpcionDeposito[];
  categorias: OpcionCategoria[];
  marcas: OpcionSimple[];
  proveedores?: OpcionSimple[];
  tipos_dispositivo?: OpcionSimple[];
  tipos_cliente?: OpcionSimple[];
  agrupaciones: OpcionAgrupacion[];
  periodo_defecto: { desde: string; hasta: string };
}

/** Envoltorio estándar: { data, meta: { filtros } }. */
export interface RespuestaReporte<T> {
  data: T;
  meta: { filtros: FiltrosReporte };
}

/** Estado de un bloque visual (gráfico o tabla) con su dato. */
export interface Bloque<T> {
  estado: EstadoCarga;
  data: T | null;
}

export interface OpcionCategoria {
  id: number;
  nombre: string;
  padre_id: number | null;
  nivel: number;
}

export interface OpcionSimple {
  id: number;
  nombre: string;
}

/** Respuesta de reportes tabulares: { data: [filas], meta: { filtros, totales } }. */
export interface RespuestaTabla<F, T> {
  data: F[];
  meta: { filtros: FiltrosReporte; totales: T };
}
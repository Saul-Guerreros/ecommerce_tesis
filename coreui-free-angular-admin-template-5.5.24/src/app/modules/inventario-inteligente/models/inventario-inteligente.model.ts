// modules/inventario-inteligente/models/inventario-inteligente.model.ts

export type TipoAlerta = 'agotado' | 'critico' | 'reponer' | 'sobrestock';

/** 1 Pendiente · 2 Atendida · 3 Descartada (igual que AlertaStock en el backend). */
export type EstadoAlerta = 1 | 2 | 3;

export interface ResumenInventario {
  alertas: Record<TipoAlerta, number>;
  total_pendientes: number;
  inversion_sugerida: number;
  valor_sobrestock: number;
  productos_analizados: number;
  ultima_ejecucion: string | null;
  modelos_elegidos: Record<string, number>;
}

export interface AlertaStock {
  id: number;
  producto_id: number;
  codigo: string | null;
  producto: string;
  deposito_id: number;
  deposito: string;
  tipo: TipoAlerta;
  disponible: number;
  demanda_diaria: number;
  stock_seguridad: number;
  punto_reorden: number;
  sugerido: number;
  costo_sugerido: number;
  cobertura_dias: number | null;
  lead_time: number;
  modelo: string | null;
  estado: EstadoAlerta;
  observacion: string | null;
  usuario: string | null;
  creado: string;
  actualizado: string;
  cerrado: string | null;
}

export interface FiltrosAlertas {
  estado: EstadoAlerta;
  tipo?: TipoAlerta | null;
  deposito?: number | null;
  search?: string;
}

export interface PuntoSerie {
  semana: string;
  unidades: number;
  modelo?: string;
}

export interface Reposicion {
  disponible: number;
  demanda_semanal: number;
  demanda_diaria: number;
  sigma: number;
  lt_dias: number;
  modelo: string;
  costo_unitario: number;
  tipo: TipoAlerta | null;
  ss: number;
  rop: number;
  objetivo: number;
  sugerido: number;
  cobertura_dias: number | null;
}

export interface MetricaProducto {
  modelo: string;
  rmse: number;
  mae: number;
  wape: number | null;
  elegido: boolean;
}

export interface PronosticoProducto {
  producto: { id: number; codigo: string | null; nombre: string; lead_time: number | null };
  deposito_id: number;
  clasificacion: 'regular' | 'intermitente' | 'insuficiente' | null;
  adi: number | null;
  historial: PuntoSerie[];
  pronostico: PuntoSerie[];
  reposicion: Reposicion | null;
  metricas: MetricaProducto[];
}

export interface MetricaModelo {
  modelo: string;
  veces_elegido: number;
  rmse: number;
  mae: number;
  mae_ponderado: number;
}

export interface ConfigInventario {
  nivel_servicio: number;
  factor_z?: number;
  horizonte: number;
  lead_time: number;
  cobertura_max: number;
}

/** Etiquetas y colores CoreUI por tipo, para badges y tarjetas. */
export const TIPOS_ALERTA: Record<TipoAlerta, { etiqueta: string; color: string; descripcion: string }> = {
  agotado:    { etiqueta: 'Agotado',    color: 'dark',    descripcion: 'Sin stock disponible y con demanda prevista' },
  critico:    { etiqueta: 'Crítico',    color: 'danger',  descripcion: 'Por debajo del stock de seguridad' },
  reponer:    { etiqueta: 'Reponer',    color: 'warning', descripcion: 'Alcanzó el punto de reorden' },
  sobrestock: { etiqueta: 'Sobrestock', color: 'info',    descripcion: 'Cobertura mayor a la máxima configurada' },
};

export const NOMBRES_MODELO: Partial<Record<string, string>> = {
  promedio_movil: 'Promedio móvil',
  ses: 'Suavizado exponencial simple',
  holt: 'Holt (tendencia)',
  croston: 'Croston',
  sba: 'SBA (Syntetos-Boylan)',
};


// ---------------- Análisis con IA (Gemini) ----------------

export type PrioridadAccion = 'alta' | 'media' | 'baja';
export type TipoAccion = 'comprar' | 'liquidar' | 'revisar_parametros' | 'monitorear';

export interface AccionInventario {
  titulo: string;
  evidencia: string;
  accion: string;
  prioridad: PrioridadAccion;
  tipo: TipoAccion;
  productos: string[];
}

export interface InsightsInventario {
  resumen: string;
  acciones: AccionInventario[];
  generado: string;
  desde_cache: boolean;
}

export const PRIORIDADES_ACCION: Record<PrioridadAccion, string> = { alta: 'danger', media: 'warning', baja: 'secondary' };

export const TIPOS_ACCION: Record<TipoAccion, string> = {
  comprar: 'Comprar',
  liquidar: 'Liquidar sobrestock',
  revisar_parametros: 'Revisar parámetros',
  monitorear: 'Monitorear',
};
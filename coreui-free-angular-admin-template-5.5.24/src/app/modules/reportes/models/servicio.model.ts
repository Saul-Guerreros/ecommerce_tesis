/** Respuestas de /api/admin/reportes/servicio/* */

export type VistaServicio = 'estados' | 'tecnicos' | 'tiempos' | 'fallas' | 'presupuestos';

export interface FiltrosServicioExtra {
  tipo_dispositivo_id?: number | null;
}

// ---------- estados ----------
export interface SolicitudPorEstado {
  id: number;
  codigo: string;
  estado: string;
  grupo: 'abierta' | 'cerrada';
  cantidad: number;
  monto: number;
  participacion: number | null;
}
export interface TotalesEstados {
  cantidad: number;
  abiertas: number;
  entregadas: number;
  canceladas: number;
  monto: number;
}

// ---------- técnicos ----------
export interface OrdenesTecnico {
  id: number;
  tecnico: string;
  asignadas: number;
  cerradas: number;
  abiertas: number;
  reparadas: number;
  parciales: number;
  no_reparables: number;
  efectividad: number | null;
  horas: number | null;
}
export interface TotalesTecnicos extends Omit<OrdenesTecnico, 'id' | 'tecnico'> {
  tecnicos: number;
}

// ---------- tiempos ----------
export interface MetricasTiempo {
  ordenes: number;
  entregadas: number;
  horas_promedio: number | null;
  horas_min: number | null;
  horas_max: number | null;
  dias_total: number | null;
}
export interface TiempoPorTipo extends MetricasTiempo {
  id: number;
  tipo: string;
}

// ---------- fallas ----------
export interface FallaFrecuente {
  id: number;
  codigo: string;
  falla: string;
  aplica: string;
  ocurrencias: number;
  reparadas: number;
  irreparables: number;
  participacion: number | null;
  tasa_reparacion: number | null;
}
export interface TotalesFallas {
  fallas: number;
  ocurrencias: number;
  diagnosticos: number;
  reparadas: number;
  irreparables: number;
}

// ---------- presupuestos ----------
export interface PresupuestoPorEstado {
  id: number;
  codigo: string;
  estado: string;
  cantidad: number;
  monto: number;
  horas_respuesta: number | null;
  participacion: number | null;
}
export interface TotalesPresupuestos {
  cantidad: number;
  aprobados: number;
  rechazados: number;
  vencidos: number;
  pendientes: number;
  tasa_aprobacion: number | null;
  monto: number;
  monto_aprobado: number;
  monto_rechazado: number;
  horas_respuesta: number | null;
}

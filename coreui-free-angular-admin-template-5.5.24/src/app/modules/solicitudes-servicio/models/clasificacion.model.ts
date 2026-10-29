/** Punto 7 - Clasificación automática de solicitudes (IA). */
export type EstadoClasificacion = 'PENDIENTE' | 'IA' | 'NO_DISPONIBLE' | 'MANUAL';
export type NivelConfianza = 'ALTA' | 'MEDIA' | 'BAJA';

export interface UrgenciaRef {
  id: number;
  codigo: string;
  descri: string;
  color: string;
}

export interface ClasificacionCorreccion {
  campo: 'DISPOSITIVO' | 'CATEGORIA' | 'URGENCIA';
  anterior: string | null;
  nuevo: string;
  motivo: string | null;
  usuario: string | null;
  fecha: string;
}

export interface Clasificacion {
  cls_id: number;
  estado: EstadoClasificacion;
  dispositivo: { id: number; descri: string } | null;
  categoria: { id: number; codigo: string; descri: string } | null;
  urgencia: UrgenciaRef | null;
  confianza: NivelConfianza | null;
  motivo: string | null;
  dispositivo_inconsistente: boolean;
  revisada: boolean;
  // Solo en el detalle
  dispositivo_cliente?: string | null;
  dispositivo_ia?: string | null;
  falla_ia?: string | null;
  correcciones?: ClasificacionCorreccion[];
  latencia_ms: number | null;
  version: string | null;
  actualizado: string | null;
}

export interface ClasificacionConfig {
  tipos_dispositivo: { tdi_id: number; tdi_descri: string }[];
  categorias: { caf_id: number; caf_codigo: string; caf_descri: string }[];
  urgencias: { urg_id: number; urg_codigo: string; urg_descri: string; urg_color: string; urg_criter: string }[];
}

export interface CorreccionPayload {
  tdi_id: number;
  caf_id: number;
  urg_id: number;
  motivo?: string | null;
}
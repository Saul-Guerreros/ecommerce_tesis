/** Respuestas de /api/admin/reportes/promociones/* */

export type VistaPromociones = 'cupones' | 'campanas';

export interface Cupon {
  id: number;
  codigo: string;
  tipo_descuento: string;
  descuento: number;
  descuento_texto: string;
  uso: string;
  alcance: string;
  elementos: number;
  estado: 'Activo' | 'Inactivo';
  creado: string;
}
export interface TotalesCupones {
  cupones: number;
  activos: number;
  inactivos: number;
  porcentaje: number;
  monto_fijo: number;
  ilimitados: number;
}

export type SituacionCampana = 'vigente' | 'programada' | 'finalizada' | 'inactiva';

export interface Campana {
  id: number;
  codigo: string;
  tipo_campana: string;
  tipo_descuento: string;
  descuento: number;
  descuento_texto: string;
  alcance: string;
  elementos: number;
  inicio: string;
  fin: string;
  duracion: number;
  situacion: SituacionCampana;
}
export interface TotalesCampanas {
  campanas: number;
  vigentes: number;
  programadas: number;
  finalizadas: number;
  inactivas: number;
  flash: number;
}

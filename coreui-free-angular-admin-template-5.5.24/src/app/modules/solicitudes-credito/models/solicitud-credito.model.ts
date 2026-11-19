/** Criterio del combo de búsqueda. Debe coincidir con el match() del Service PHP. */
export type CampoBusquedaCredito = 'numero' | 'ci' | 'cliente';

export interface SolicitudCreditoLista {
  scr_id: number;
  numero: string;
  fecha: string;
  fecha_completa: string;
  cliente: { nombre: string; cedula: string };
  total_contado: number;
  total: number;
  plazo: number;
  estado: { codigo: string; descripcion: string };
}

export interface SolicitudCreditoFiltros {
  campo?: CampoBusquedaCredito;
  q?: string;
  estado?: number | null;
  desde?: string;
  hasta?: string;
  page?: number;
  per_page?: number;
}

export interface SolicitudesCreditoPaginadas {
  data: SolicitudCreditoLista[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    desde: number;
    hasta: number;
    /** Contador global: NO depende de los filtros aplicados. */
    pendientes: number;
  };
}

export interface EstadoSolicitudOption {
  esc_id: number;
  esc_codigo: string;
  esc_descri: string;
}

export interface MotivoRechazoOption {
  codigo: string;
  etiqueta: string;
}

export interface CampoBusquedaOption {
  codigo: CampoBusquedaCredito;
  etiqueta: string;
}

export interface SolicitudesCreditoConfig {
  estados: EstadoSolicitudOption[];
  motivos_rechazo: MotivoRechazoOption[];
  campos_busqueda: CampoBusquedaOption[];
  vigencia_default_dias: number;
}
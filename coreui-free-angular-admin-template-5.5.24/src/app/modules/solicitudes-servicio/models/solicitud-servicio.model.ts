export interface EstadoRef {
  codigo: string | null;
  descri: string | null;
}

export interface SolicitudServicioLista {
  sse_id: number;
  sse_numero: string;
  sse_creado: string;
  cliente: string | null;
  cedula: string | null;
  equipo: string;
  sse_garant: boolean;
  a_domicilio: boolean;
  sse_fecing: string | null;
  estado_servicio: EstadoRef;
  estado_ingreso: EstadoRef;
  tecnico?: string | null;
}

export interface SolicitudAdjunto {
  sad_id: number;
  sad_tipo: 'CLIENTE' | 'INGRESO' | 'DIAGNOSTICO';
  sad_nombre: string | null;
  url: string;
  sad_subido: string;
}

export interface RecepcionServicio {
  rse_id: number;
  rse_fecha: string;
  rse_estfis: string;
  rse_obs: string | null;
  tiene_clave: boolean;
  recibido_por?: string | null;
  tecnico?: { usu_id: number; nombre: string | null };
  accesorios?: { rac_id: number; rac_descri: string; rac_devuel: boolean }[];
}

export interface HistorialServicio {
  she_id: number;
  she_tipo: string;
  estado_ant: string | null;
  estado_nuevo: string | null;
  she_coment: string | null;
  she_fecha: string;
  usuario?: string | null;
}

export interface SolicitudServicioDetalle {
  sse_id: number;
  sse_numero: string;
  sse_creado: string;
  cliente: { nombre: string | null; cedula: string | null; telefono: string | null; correo: string | null };
  equipo: { tipo: string | null; sse_marca: string; sse_modelo: string; sse_nroser: string | null };
  sse_proble: string;
  sse_acceso: string | null;
  sse_garant: boolean;
  pedido?: string | null;
  ingreso: {
    a_domicilio: boolean;
    modalidad: string;
    sucursal: string | null;
    direccion: string | null;
    referencia: string | null;
    sse_fecing: string | null;
    sse_cosret: number;
  };
  estado_servicio: EstadoRef;
  estado_ingreso: EstadoRef;
  adjuntos: SolicitudAdjunto[];
  recepcion: RecepcionServicio | null;
  historial: HistorialServicio[];
}

export interface TecnicoOpcion {
  usu_id: number;
  nombre: string;
}

export interface SolicitudesConfig {
  estados_servicio: { ets_id: number; ets_codigo: string; ets_descri: string }[];
  estados_ingreso: { elg_id: number; elg_codigo: string; elg_descri: string }[];
  tipos_entrega: { ten_id: number; ten_codigo: string; ten_descri: string }[];
  tecnicos: TecnicoOpcion[];
}

export interface SolicitudFiltros {
  buscar?: string;
  ets_id?: string;
  elg_id?: string;
  ten_id?: string;
  garantia?: string;
  solo_pendientes?: boolean;
  page?: number;
  per_page?: number;
}

export interface SolicitudesPaginadas {
  data: SolicitudServicioLista[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; desde: number; hasta: number };
}

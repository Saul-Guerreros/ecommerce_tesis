import { PresupuestoResumen, UltimoContacto } from '../../panel-servicios/models/panel-servicio.model';

export type Canal = 'WEB' | 'MOSTRADOR';

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
  canal: Canal;
  sucursal: string | null;
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
  medio?: string | null;
  resultado?: string | null;
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
  canal: Canal;
  registrado_por?: string | null;
  cliente: {
    nombre: string | null;
    cedula: string | null;
    telefono: string | null;
    correo: string | null;
    whatsapp: string | null;
    tiene_cuenta: boolean;
    whatsapp_url: string | null;
  };
  equipo: { tipo: string | null; sse_marca: string; sse_modelo: string; sse_nroser: string | null };
  sse_proble: string;
  sse_acceso: string | null;
  sse_garant: boolean;
  pedido?: string | null;
  ingreso: {
    a_domicilio: boolean;
    modalidad: string;
    suc_id: number | null;
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
  // Paso 2
  presupuesto: PresupuestoResumen | null;
  ultimo_contacto: UltimoContacto | null;
  cerrada: boolean;
  // Paso 3
  garantia_vence: string | null;
  puede_reclamar: boolean;
  es_reclamo_de: string | null;
}

export interface TecnicoOpcion {
  usu_id: number;
  nombre: string;
  /** Sucursales (taller) a las que está asignado. Sin sucursal no aparece en ninguna. */
  sucursales: number[];
}

export interface SucursalOpcion {
  suc_id: number;
  suc_nombre: string;
}

export interface SolicitudesConfig {
  estados_servicio: { ets_id: number; ets_codigo: string; ets_descri: string }[];
  estados_ingreso: { elg_id: number; elg_codigo: string; elg_descri: string }[];
  tipos_entrega: { ten_id: number; ten_codigo: string; ten_descri: string }[];
  tecnicos: TecnicoOpcion[];
  sucursales: SucursalOpcion[];
  canales: Canal[];
}

export interface SolicitudFiltros {
  buscar?: string;
  ets_id?: string;
  elg_id?: string;
  ten_id?: string;
  garantia?: string;
  suc_id?: string;
  canal?: string;
  solo_pendientes?: boolean;
  page?: number;
  per_page?: number;
}

export interface SolicitudesPaginadas {
  data: SolicitudServicioLista[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; desde: number; hasta: number };
}

export interface PanelResumen {
  por_estado: { codigo: string; descri: string; total: number }[];
  tarjetas: {
    pendientes_recepcion: number;
    en_taller: number;
    presupuestos_validar: number;
    esperando_cliente: number;
    por_vencer: number;
    sin_contacto: number;
    listos_entregar: number;
    cobros_pendientes: number;
  };
  tecnicos: { usu_id: number; nombre: string; abiertas: number; sucursales: number[] }[];
}

export interface PanelFila {
  sse_id: number;
  sse_numero: string;
  ose_numero: string | null;
  sse_creado: string;
  cliente: string | null;
  equipo: string;
  sse_garant: boolean;
  es_reclamo: boolean;
  canal: 'WEB' | 'MOSTRADOR';
  sucursal: { suc_id: number; nombre: string | null } | null;
  dias_taller: number | null;
  ejes: {
    servicio: { codigo: string; descri: string };
    ingreso: string | null;
    pago: { codigo: string; descri: string } | null;
    egreso: string | null;
  };
  presupuesto: PresupuestoResumen | null;
  ultimo_contacto: UltimoContacto | null;
  whatsapp_url: string | null;
  sse_total: number;
  tecnico: { usu_id: number; nombre: string | null } | null;
  acciones: {
    reasignar: boolean; entregar: boolean; const_recepcion: boolean; const_retiro: boolean;
    responder: boolean; contacto: boolean; pdf_presupuesto: boolean;
  };
}

/** Paso 2 - Presupuesto vigente (panel y detalle). */
export interface PresupuestoResumen {
  pse_id: number;
  numero: string;
  versio: number;
  codigo: string;
  estado: string;
  total: number;
  fecven: string | null;
  vencido: boolean;
  respuesta: {
    fecha: string; canal: string | null; etiqueta: string | null;
    registro: string | null; obs: string | null; motivo: string | null;
  } | null;
  puede_responder: boolean;
  puede_imprimir: boolean;
}

export interface UltimoContacto {
  fecha: string;
  medio: string | null;
  resultado: string | null;
  codigo?: string | null;
}

export type CanalRespuesta = 'MOSTRADOR' | 'TELEFONO' | 'WHATSAPP';
export type MedioContacto = 'TELEFONO' | 'WHATSAPP' | 'CORREO' | 'PRESENCIAL';
export type ResultadoContacto = 'CONTACTADO' | 'NO_CONTESTA' | 'PIDE_TIEMPO';

export interface PanelConfig {
  estados_servicio: { ets_id: number; ets_codigo: string; ets_descri: string }[];
  tecnicos: { usu_id: number; nombre: string; sucursales: number[] }[];
  sucursales: { suc_id: number; suc_nombre: string }[];
  canales: ('WEB' | 'MOSTRADOR')[];
  contacto_alerta_dias: number;
}

export interface EntregaConfig {
  /** Paso 3: canal mostrador -> solo retiro en la sucursal de la solicitud (sin envío). */
  solo_retiro_sucursal: boolean;
  sse_numero: string;
  cliente: string | null;
  equipo: string;
  ingreso_a_domicilio: boolean;
  sse_dirid: number | null;
  sse_sucid: number | null;
  titular: { nrodoc: string | null; nombre: string | null; apelli: string | null; telef: string | null };
  sucursales: { suc_id: number; suc_nombre: string }[];
  direcciones: { dir_id: number; resumen: string }[];
  accesorios: { rac_id: number; rac_descri: string }[];
  cobro: { habilitado: boolean; motivo: string | null; total: number };
}

export interface PanelPaginado {
  data: PanelFila[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; desde: number; hasta: number };
}

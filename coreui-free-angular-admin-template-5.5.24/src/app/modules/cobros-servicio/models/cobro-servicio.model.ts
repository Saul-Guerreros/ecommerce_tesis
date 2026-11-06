/** Pago del servicio, vista del panel (ServicioPagoResource). */
export interface PagoServicio {
  spa_id: number;
  orden: number;
  tipo: 'ANTICIPO' | 'SALDO' | 'TOTAL';
  canal: 'WEB' | 'MOSTRADOR';
  creado: string | null;
  metodo: { mpa_id: number | null; codigo: string | null; descri: string | null; requcom: boolean };
  monto_asignado: number;
  estado: { codigo: string | null; descripcion: string | null };
  comprobante: { sco_id: number; archivo: string; url: string; verificado: boolean; subido: string | null } | null;
  verificacion: {
    monto_verificado: number | null; nro_transaccion: string | null; banco: string | null;
    fecha_pago: string | null; por: string | null; fecha: string | null;
  } | null;
  cobro: { por: string | null; sucursal: string | null } | null;
  rechazo: { motivo_id: number; motivo: string | null; motivo_codigo: string | null; detalle: string | null; faltante: number | null } | null;
  observacion: string | null;
}

/** EstadoPagoServicioService::resumen() */
export interface ResumenPagoServicio {
  total: number;
  consolidado: boolean;
  confirmado: number;
  en_verificacion: number;
  comprometido: number;
  saldo: number;
  admite_pagos: boolean;
}

/** GET /admin/servicios/cobros/{id}: sección "Pagos" del detalle y de A5. */
export interface CobroDetalle {
  sse_id: number;
  sse_numero: string;
  estado_pago: { codigo: string; descripcion: string } | null;
  resumen: ResumenPagoServicio;
  pagos: PagoServicio[];
  /** Lo decide el backend (permiso + sucursal + si admite pagos). */
  acciones: { cobrar: boolean; verificar: boolean };
}

export interface MetodoPagoOpcion { mpa_id: number; mpa_codigo: string; mpa_descri: string; mpa_requcom: boolean }
export interface MotivoRechazo { mrp_id: number; mrp_codigo: string; mrp_descri: string; mrp_requdet: boolean }

export interface CobroConfig {
  estados: { epa_id: number; epa_codigo: string; epa_descri: string }[];
  metodos: MetodoPagoOpcion[];
  motivos: MotivoRechazo[];
  sucursales: { suc_id: number; suc_nombre: string }[];
  permisos: { cobrar: boolean; verificar: boolean };
}

/** Fila de la bandeja (CobroServicioListaResource). */
export interface CobroFila {
  spa_id: number;
  orden: number;
  tipo: string;
  canal: 'WEB' | 'MOSTRADOR';
  creado: string | null;
  metodo: string | null;
  metodo_codigo: string | null;
  monto: number;
  estado: { codigo: string | null; descripcion: string | null };
  tiene_comprobante: boolean;
  solicitud: {
    sse_id: number; sse_numero: string; estado: string | null; total: number;
    sucursal: string | null; cliente: string | null; cedula: string | null;
  };
}

export interface CobroFiltros {
  buscar: string;
  estado: string;
  canal: string;
  mpa_id: string | number;
  suc_id: string | number;
  desde: string;
  hasta: string;
}

export interface CobrosPaginado {
  data: CobroFila[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; desde: number; hasta: number };
}

/** Respuesta de toda acción sobre pagos: la sección se refresca con 'data'. */
export interface RespuestaCobro {
  message: string;
  data: CobroDetalle;
}

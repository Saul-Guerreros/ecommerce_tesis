import { FacturaAnulada, FacturaServicio } from './factura-servicio.model';

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
  /** Ya está declarado en una factura emitida (no se rechaza: corresponde NC). */
  facturado: boolean;

  comprobante: {
    sco_id: number;
    archivo: string;
    url: string;
    subido: string | null;
    validado: boolean;
    validacion: { observacion: string | null; por: string | null; fecha: string } | null;
  } | null;

  /** Cobro registrado contra la factura (cobro_venta). */
  cobro: {
    cob_id: number;
    monto: number;
    metodo: string | null;
    nro_transaccion: string | null;
    banco: string | null;
    fecha: string | null;
    observacion: string | null;
    por: string | null;
    registrado: string | null;
    puede_anular: boolean;
  } | null;

  /** Quién lo registró en el mostrador y dónde. */
  cajero: { por: string | null; sucursal: string | null } | null;

  puede_validar: boolean;
  puede_rechazar: boolean;
  puede_cobrar: boolean;

  /** Ciclo anterior (sin cobro_venta). */
  verificacion: {
    monto_verificado: number | null; nro_transaccion: string | null; banco: string | null;
    fecha_pago: string | null; por: string | null; fecha: string | null;
  } | null;

  rechazo: { motivo_id: number; motivo: string | null; motivo_codigo: string | null; detalle: string | null; faltante: number | null } | null;
  observacion: string | null;
}

/** EstadoPagoServicioService::resumen() */
export interface ResumenPagoServicio {
  total: number;
  consolidado: boolean;
  anticipo: number;
  confirmado: number;
  en_verificacion: number;
  comprometido: number;
  saldo: number;
  admite_pagos: boolean;
}

/** Lo decide el backend (permiso + sucursal + estado). */
export interface AccionesCobro {
  declarar_anticipo: boolean;
  tope_anticipo: number | null;
  generar_anticipo: boolean;
  generar_factura: boolean;
  saldo_por_declarar: number;
  declarar_medios: boolean;
  cobrar_entregar: boolean;
  verificar: boolean;
  cobrar: boolean;
}

export interface DevolucionItem {
  dev_id: number;
  monto: number;
  metodo: string | null;
  nro_transaccion: string | null;
  banco: string | null;
  fecha: string | null;
  observacion: string | null;
  por: string | null;
  anulada: boolean;
  motivo_anulacion: string | null;
  puede_anular: boolean;
}

export interface DevolucionNota {
  ncv_id: number;
  factura: 'ANTICIPO' | 'FINAL';
  numero: string;
  fecha: string | null;
  total: number;
  anulada: boolean;
  fe: { estado: 'PENDIENTE' | 'APROBADA' | 'RECHAZADA'; cdc: string | null } | null;
  devuelto: number;
  por_devolver: number;
  puede_devolver: boolean;
  devoluciones: DevolucionItem[];
}

/** Bloque "Devoluciones" (CobroServicioConsultaService::devolucion). */
export interface DevolucionServicio {
  disponible: number;
  por_devolver: number;
  puede_acreditar: boolean;
  liberacion: { aplicado: number; liberado: number; fecha: string | null; automatica: boolean } | null;
  puede_liberar: boolean;
  notas: DevolucionNota[];
}

/** GET /admin/servicios/cobros/{id}: sección "Pagos" del detalle y de A5. */
export interface CobroDetalle {
  sse_id: number;
  sse_numero: string;
  estado_pago: { codigo: string; descripcion: string } | null;
  resumen: ResumenPagoServicio;
  pagos: PagoServicio[];
  facturas: { anticipo: FacturaServicio | null; final: FacturaServicio | null };
  acciones: AccionesCobro;
  devolucion: DevolucionServicio | null;
  anuladas: FacturaAnulada[];
}

/** Medio que el cajero declara antes de emitir una factura. */
export interface MedioDeclarado { mpa_codigo: string; monto: number }

export interface MetodoPagoOpcion { mpa_id: number; mpa_codigo: string; mpa_descri: string; mpa_requcom: boolean }
export interface MotivoRechazo { mrp_id: number; mrp_codigo: string; mrp_descri: string; mrp_requdet: boolean }

export interface CobroConfig {
  estados: { epa_id: number; epa_codigo: string; epa_descri: string }[];
  metodos: MetodoPagoOpcion[];
  motivos: MotivoRechazo[];
  sucursales: { suc_id: number; suc_nombre: string }[];
  permisos: { cobrar: boolean; verificar: boolean; nota_credito: boolean };
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

/** Medios que se pueden declarar en el mostrador (MediosPagoServicioRequest). */
export const MEDIOS_MOSTRADOR = ['CONTRAENTREGA', 'TRANSFERENCIA', 'BILLETERA_MOVIL']; 
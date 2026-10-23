import { DatosBilletera, DatosTransferencia, MetodoPagoOpcion, SucursalRetiro } from './checkout.model';
import { Direccion } from './direccion.model';

export type ModalidadIngreso = 'sucursal' | 'domicilio';

export interface TipoDispositivoOpcion {
  tdi_id: number;
  tdi_codigo: string;
  tdi_descri: string;
}

/** Producto comprado y entregado que todavía está dentro de su plazo de garantía. */
export interface LineaGarantia {
  dpd_id: number;
  ped_id: number;
  ped_numero: string;
  producto: string;
  fecha_entrega: string;   // Y-m-d
  vence: string;           // Y-m-d (el último día cuenta completo)
  dias_restantes: number;  // 0 = vence hoy
  dias_garantia: number;
}

export interface ConfigServicio {
  tipos_dispositivo: TipoDispositivoOpcion[];
  sucursales: SucursalRetiro[];
  direcciones: Direccion[];
  lineas_garantia: LineaGarantia[];
  garantia_dias: number;   // plazo general: solo para productos sin plazo propio
  max_fotos: number;
}

/** Vista del cliente: solo el estado público (ets_visible). */
export interface ServicioCliente {
  sse_numero: string;
  sse_creado: string;
  equipo: string;
  sse_nroser: string | null;
  sse_garant: boolean;
  estado: string;
  /** Pasos del timeline de este servicio (la garantía de compra tiene los suyos). */
  pasos?: string[];
  /** 1..5 dentro de `pasos`; null si está SOLICITADO o CANCELADO. */
  paso_actual: number | null;
  cancelado: boolean;
  accion_pendiente: string | null;

  // solo en el detalle
  sse_proble?: string;
  sse_acceso?: string | null;
  pedido?: string | null;
  /** Garantía de compra: producto cubierto. */
  producto?: string | null;
  /** Garantía de compra: gestión con la empresa responsable (el taller hace el trámite). */
  garantia_empresa?: GarantiaEmpresaCliente | null;
  ingreso?: {
    a_domicilio: boolean;
    sucursal: string | null;
    direccion: string | null;
    fecha: string | null;
    costo: number;
    estado: string | null;
  };
  fotos?: string[];
  historial?: { fecha: string; coment: string | null }[];
  presupuesto?: PresupuestoCliente | null;

  // Paso 6
  equipo_datos?: { tdi_id: number; marca: string; modelo: string; nroser: string | null };
  reclamo_de?: string | null;
  resultado?: { resultado: string; detalle: string | null; total: number } | null;
  entrega?: { fecha: string; receptor: string } | null;
  garantia?: { dias: number; vence: string; vigente: boolean; puede_reclamar: boolean; reclamo_abierto: boolean } | null;
  constancias?: { recepcion: boolean; retiro: boolean };

  // Pago del servicio (solo en el detalle; null si no genera cobro)
  pago?: PagoServicioBloque | null;
}

// ---------- Pago del servicio (C4) ----------

/** Un pago tal como lo ve el cliente (ServicioPagoClienteResource). */
export interface PagoServicioCliente {
  spa_id: number;
  orden: number;
  metodo: string | null;
  monto: number;
  estado: string | null;
  estado_descri: string | null;
  tiene_comprobante: boolean;
  comprobante_url: string | null;
  motivo_rechazo: { motivo: string | null; detalle: string | null } | null;
  reemplazable: boolean;
}

/** Bloque "pago" del detalle C4. 'pagable' lo decide el backend. */
export interface PagoServicioBloque {
  estado: string | null;
  total: number;
  pagado: number;
  en_verificacion: number;
  saldo: number;
  pagable: boolean;
  pagos: PagoServicioCliente[];
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

export interface ConfigPagoServicioResponse {
  servicio: { sse_numero: string };
  resumen: ResumenPagoServicio;
  metodos: MetodoPagoOpcion[];
  datos_transferencia: DatosTransferencia;
  datos_billetera: DatosBilletera;
  mensaje_efectivo: string | null;
  pagos: PagoServicioCliente[];
}

export interface GarantiaEmpresaCliente {
  empresa: string | null;
  nrocaso: string | null;
  fecha_envio: string | null;
  fecha_recepcion: string | null;
  resultado: string | null;
  codigo_resultado: 'REPARADO' | 'REEMPLAZADO' | 'RECHAZADO' | null;
  nuevo_serie: string | null;
  motivo: string | null;
}

export interface PresupuestoCliente {
  pse_numero: string;
  pse_versio: number;
  estado: string;
  codigo: 'ENVIADO' | 'APROBADO' | 'RECHAZADO' | 'VENCIDO';
  pse_fecven: string | null;
  pse_obs: string | null;
  pse_motrec: string | null;
  /** Paso 2: cómo se registró la respuesta (asistida = la cargó la sucursal). */
  respuesta?: { fecha: string; canal: string | null; etiqueta: string | null; asistida: boolean } | null;
  pse_impues: number;
  pse_total: number;
  costo_retiro: number;
  puede_responder: boolean;
  lineas: { tipo: 'REPUESTO' | 'MANO_OBRA'; descri: string; cantid: number; precio: number; total: number }[];
}

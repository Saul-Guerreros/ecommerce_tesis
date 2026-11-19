import {
  ChequeDeclarado, DatosBilletera, DatosCheque, DatosTransferencia,
  MetodoPagoCodigo, MetodoPagoOpcion, ModoPago, PagoDividRequest,
} from './checkout.model';

/**
 * Pago de cuotas: pantalla de pago y pantalla de resultado.
 *
 * Reutiliza los tipos del Paso 3 del checkout (métodos, cheque, pago
 * dividido) a propósito: son literalmente las mismas formas de pago, y
 * los componentes app-pago-dividido y app-cheque-form se comparten.
 */

export interface CuotaAPagar {
  cco_id: number;
  numero: number;
  vencimiento: string;
  importe: number;
}

export interface SucursalCuotas {
  nombre: string | null;
  direccion: string | null;
  horario: string | null;
}

export interface ConfigPagoCuotasResponse {
  pedido: { numero: string };
  factura: { numero: string };
  cuotas: CuotaAPagar[];
  total: number;

  metodos: MetodoPagoOpcion[];
  datos_transferencia: DatosTransferencia;
  datos_billetera: DatosBilletera;
  mensaje_efectivo: string | null;
  datos_cheque: DatosCheque;
  cheque_habilitado: boolean;
  cheque_motivo: string | null;

  sucursal: SucursalCuotas | null;
}

export interface RegistrarPagoCuotasRequest {
  modo: ModoPago;
  mpa_codigo?: MetodoPagoCodigo;
  comprobante?: File | null;
  /** Sólo se completa cuando mpa_codigo es CHEQUE. */
  cheque?: ChequeDeclarado | null;
  pagos?: PagoDividRequest[];
}

// ---------- Pantalla de resultado ----------

/**
 * REVISION -> todo el pago lleva comprobante  ("Comprobante enviado")
 * PARCIAL  -> comprobante + efectivo o cheque ("Comprobante enviado")
 * CHEQUE   -> cheque, sin comprobantes        ("Pago seleccionado")
 * EFECTIVO -> sólo efectivo                   ("Pago seleccionado")
 *
 * Lo decide el backend a partir del catálogo metodo_pago.
 */
export type CasoResultadoCuota = 'REVISION' | 'PARCIAL' | 'CHEQUE' | 'EFECTIVO';

export interface MedioResultadoCuota {
  cpm_id: number;
  orden: number;
  mpa_codigo: MetodoPagoCodigo;
  mpa_descri: string;
  monto: number;
  tiene_comprobante: boolean;
}

export interface ChequeResultadoCuota {
  monto: number;
  cheques: { orden: number; monto: number }[];
  titular: string | null;
  vence: string | null;
  horas_plazo: number;
  sucursal: SucursalCuotas | null;
}

export interface ResultadoPagoCuota {
  cpa_id: number;
  ped_numero: string;
  fecha: string | null;
  caso: CasoResultadoCuota;

  total: number;
  monto_en_revision: number;
  monto_efectivo: number;

  cuotas: { numero: number; importe: number }[];
  medios: MedioResultadoCuota[];
  cheque: ChequeResultadoCuota | null;
}
/** Una factura a crédito en la cartera (fila del listado). */
export interface CuentaPorCobrar {
  ven_id: number;
  ven_cliid: number;
  ven_nrodoc: string;
  ven_cli_razon: string;
  ven_cli_doc: string;
  ven_fchaem: string;
  ven_total: number;
  cuo_total: number;
  cuo_pendientes: number;
  cuo_saldo: number;
  cuo_proxvencim: string | null;
  /** Tiene al menos una cuota pendiente con vencimiento pasado. */
  vencida: boolean;
}

export interface ReciboCuota {
  rec_id: number;
  rec_numero: string | null;
  /** Total del RECIBO, que puede cubrir varias cuotas. */
  rec_monto: number;
  rec_fecha: string;
  rec_nrotrx: string | null;
  rec_banco: string | null;
  rec_observ: string | null;
  metodo: string | null;
}

export interface CuotaCobrar {
  cco_id: number;
  cco_nrocuo: number;
  cco_femisi: string;
  cco_vencim: string;
  cco_monto: number;
  cco_saldo: number;
  /** Qué productos componen la cuota: explica por qué varía el importe. */
  cco_compos: string | null;
  cco_estado: 'PENDIENTE' | 'COBRADA' | 'ANULADA';
  cco_feccob: string | null;
  vencida: boolean;
  /** Lo decide el backend: la pantalla repite la regla, no la calcula. */
  puede_cobrar: boolean;
    recibo: ReciboCuota | null;
}

export interface FacturaCredito {
  ven_id: number;
  ven_nrodoc: string;
  ven_fchaem: string;
  ven_total: number;
  ven_cli_razon: string;
  ven_cli_doc: string;
  ven_estado: number;
  cant_cuotas: number;
}

export interface DetalleCuentaPorCobrar {
  factura: FacturaCredito;
  cuotas: CuotaCobrar[];
  resumen: {
    saldo_factura: number;
    /** Deuda del cliente en TODAS sus facturas, no solo en ésta. */
    deuda_cliente: number;
  };
}

export interface ClienteOption {
  ven_cliid: number;
  ven_cli_razon: string;
}

export interface MetodoPagoOption {
  mpa_id: number;
  mpa_descri: string;
}

/**
 * Un recibo puede cubrir varias cuotas. El monto no viaja: lo arma el
 * backend sumando las cuotas, porque no se aceptan pagos parciales.
 */
export interface NuevoCobroCuota {
  cuotas: number[];
  rec_mpaid: number;
  rec_fecha: string;
  rec_nrotrx?: string | null;
  rec_banco?: string | null;
  rec_observ?: string | null;
}
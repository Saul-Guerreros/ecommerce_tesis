export interface MotivoNotaCredito {
  mnc_id: number;
  mnc_codigo: string;
  mnc_descri: string;
  mnc_afesto: number;                       // 1 = afecta cantidad de stock
  mnc_estado: number;
}

export interface LineaNCConfig {
  cmd_id: number;
  prd_codinv: string | null;
  cmd_descri: string;
  cmd_tasa: number;
  cmd_canfac: number;
  cmd_precun: number;
  precio_neto: number;                      // por unidad de stock, IVA incluido
  saldo_acreditable: number;
  stock_disponible: number;
}

export interface TipoComprobanteNC {
  tco_id: number;
  tco_descri: string;
}

export interface NotaCreditoConfig {
  com_id: number;
  com_proid: number;
  com_nrodoc: string;
  com_proraz: string;
  com_proruc: string;
  com_fchaem: string;
  tipo_nota_credito: TipoComprobanteNC | null;
  motivos: MotivoNotaCredito[];
  lineas: LineaNCConfig[];
}

// Lo que el formulario ENVIA: el importe lo calcula el backend.
export interface DetNotaCreditoInput {
  cmd_id: number;
  cantidad: number;
  descuento_unitario?: number;              // solo en motivos que NO afectan stock
}

// Lo que el backend DEVUELVE.
export interface DetNotaCreditoResumen {
  dnc_id: number;
  dnc_cmdid: number;
  dnc_cantidad: number;
  dnc_despor: number;
  dnc_descuento_unitario: number;
  dnc_base_gravada: number;                 // IVA incluido
  dnc_iva: number;                          // IVA contenido
  dnc_total: number;
  dnc_canaju: number;
  cmd_descri: string | null;
  prd_codinv: string | null;
}

export interface NotaCredito {
  ncr_id?: number;
  ncr_comid?: number;
  ncr_tpvid: number | null;
  ncr_timbra?: string | null;
  ncr_timbvn?: string | null;
  ncr_numero: string;
  ncr_fecha: string;
  ncr_mncid: number | null;
  motivo?: MotivoNotaCredito | null;
  ncr_obs?: string | null;
  ncr_gravada?: number;
  ncr_iva?: number;
  ncr_total?: number;
  detalle: DetNotaCreditoInput[] | DetNotaCreditoResumen[];
}
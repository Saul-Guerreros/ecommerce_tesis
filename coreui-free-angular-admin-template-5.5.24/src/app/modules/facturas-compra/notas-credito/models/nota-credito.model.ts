export interface LineaNCConfig {
  cmd_id: number;
  prd_codinv: string | null;
  cmd_descri: string;
  cmd_tasa: number;
  cmd_canfac: number;
  cmd_precun: number;
  saldo_acreditable: number;
}

export interface NotaCreditoConfig {
  com_id: number;
  com_nrodoc: string;
  com_proraz: string;
  lineas: LineaNCConfig[];
}

// Lo que el formulario ENVIA al backend al registrar la NC.
export interface DetNotaCreditoInput {
  cmd_id: number;
  cantidad: number;
  base_gravada: number;
  iva?: number;
}

// Lo que el backend DEVUELVE (NotaCreditoResource / DetNotaCreditoResource).
export interface DetNotaCreditoResumen {
  dnc_id: number;
  dnc_cmdid: number;
  dnc_cantidad: number;
  dnc_base_gravada: number;
  dnc_iva: number;
  dnc_total: number;
  dnc_canaju: number;
  cmd_descri: string | null;
  prd_codinv: string | null;
}

export interface NotaCredito {
  ncr_id?: number;
  ncr_comid?: number;
  ncr_numero: string;
  ncr_fecha: string;
  ncr_motivo: string;
  ncr_obs?: string | null;
  ncr_gravada?: number;
  ncr_iva?: number;
  ncr_total?: number;
  detalle: DetNotaCreditoInput[] | DetNotaCreditoResumen[];
}
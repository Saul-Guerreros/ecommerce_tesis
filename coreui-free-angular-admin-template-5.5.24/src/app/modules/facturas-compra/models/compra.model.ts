export interface CompraDetalle {
  cmd_id?: number;
  prd_id: number | null;
  tiv_id: number | null;
  descri: string;
  costo: number | null;
  despor?: number;
  descto?: number;
  tasa: number;          // tasa de IVA de esa linea (viene del producto)
  gravada?: number;
  iva?: number;
  canfac: number | null;
  canrec: number | null; // en esta version, canrec = canfac (sin recepcion parcial)

  // solo para mostrar en la grilla, no se envian
  prd_codinv?: string;
}

export interface Compra {
  com_id?: number;
  com_proid: number | null;
  com_depid: number | null;
  com_sucid?: number | null;   // derivado del deposito elegido, solo lectura
  com_tcoid: number | null;

  com_proruc?: string;
  com_proraz?: string;
  com_timbra: string | null;
  com_timbvn: string | null;
  com_nrodoc: string;
  com_fchaem: string;
  com_nrorem?: string | null;

  com_condic: number;      // 0 contado, 1 credito
  com_plzdia?: number | null;
  com_cuotas?: number | null;
  com_interv?: number | null;
  com_fchvto?: string | null;

  com_exenta?: number;
  com_grav5?: number;
  com_grav10?: number;
  com_iva5?: number;
  com_iva10?: number;
  com_total?: number;

  com_estfac?: number;     // 1 Confirmada, 2 Anulada
  com_obs?: string | null;
  com_creado?: string;
  com_fecanu?: string | null;

  suc_nombre?: string;
  dpo_nombre?: string;
  tco_descri?: string;

  detalle?: CompraDetalle[];
}

export interface ProveedorOption {
  pro_id: number;
  pro_razon: string;
  pro_ruc: string;
}

export interface DepositoOption {
  dpo_id: number;
  dpo_nombre: string;
  dpo_sucid: number;
  suc_nombre: string;
}

export interface TipoComprobanteOption {
  tco_id: number;
  tco_descri: string;
  tco_reqtim: number;
}

export interface TipoIvaOption {
  tiv_id: number;
  tiv_descri: string;
  tiv_tasa: number;
}

export interface CompraConfig {
  proveedores: ProveedorOption[];
  depositos: DepositoOption[];
  tipos_comprobante: TipoComprobanteOption[];
  tipos_iva: TipoIvaOption[];
}

export interface ProductoBusqueda {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  prd_prevta: number;
  prd_tivid?: number;
  tiv_tasa?: number;
}
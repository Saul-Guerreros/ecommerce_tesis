export interface CompraDetalle {
  cmd_id?: number;
  prd_id: number | null;
  umd_id: number | null;   // unidad elegida para ESTA linea
  
  peq_id?: number | null;   // presentación usada (null = unidad de stock)

  tiv_id: number | null;
  descri: string;

  precun: number | null;   // precio por 1 umd_id (lo que dice la factura)
  despor?: number;   // ayuda de carga (se recalcula desde descto)
  descto?: number;   // descuento UNITARIO en Gs. — fuente de verdad
  tasa: number;             // tasa de IVA de esa linea (viene del producto)
  gravada?: number;
  iva?: number;

  canfac: number | null;   // cantidad en umd_id (ej. 2 cajas)
  canrec: number | null;   // idem, cantidad recibida

  // solo para mostrar en la grilla, no se envian
  prd_codinv?: string;
  umd_codigo?: string;     // solo lectura, para el listado

  
}

export interface Compra {
  com_id?: number;
  com_proid: number | null;
  com_depid: number | null;
  com_sucid?: number | null;   // derivado del deposito elegido, solo lectura
  com_tcoid: number | null;
  com_tpvid?: number | null;   // NUEVO: timbrado del proveedor elegido

  com_proruc?: string;
  com_proraz?: string;
  com_timbra?: string | null;  // snapshot (solo lectura, lo arma el backend)
  com_timbvn?: string | null;  // snapshot (solo lectura, lo arma el backend)
  com_nrodoc: string;
  com_fchaem: string;

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

export interface SucursalOption {
  suc_id: number;
  suc_nombre: string;
}

export interface DepositoOption {
  dpo_id: number;
  dpo_nombre: string;
  dpo_sucid: number;
  dpo_princi?: number;
  suc_nombre: string;
}

// TimbradoProveedorOption: eliminar tpv_fechin
export interface TimbradoProveedorOption {
  tpv_id: number;
  tpv_proid: number;
  tpv_tcoid: number;
  tpv_numero: string;
  tpv_fechfn: string;   // YYYY-MM-DD
  tpv_estado: number;
  tco_descri?: string;
}

export interface NuevoTimbradoProveedor {
  tpv_proid: number;
  tpv_tcoid: number;
  tpv_numero: string;
  tpv_fechfn: string;
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
  sucursales: SucursalOption[];
  depositos: DepositoOption[];
  tipo_factura: TipoComprobanteOption | null;   // reemplaza tipos_comprobante
  tipos_iva: TipoIvaOption[];
}

export interface ProductoBusqueda {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  prd_prevta: number;
  prd_tivid?: number;
  tiv_tasa?: number;
  prd_umdid: number;      // unidad de stock del producto (unidad base)
  umd_codigo?: string;    // nombre/codigo de esa unidad, solo lectura
}

export interface UnidadCompraOption {
  umd_id: number;
  peq_id: number | null;    // null para la unidad base
  umd_codigo: string;
  umd_nombre: string;
  factor: number;           // 1 para la base; el de producto_equivalencia para las alternativas
  etiqueta: string;         // "Unidad" · "Caja (x10)"
}
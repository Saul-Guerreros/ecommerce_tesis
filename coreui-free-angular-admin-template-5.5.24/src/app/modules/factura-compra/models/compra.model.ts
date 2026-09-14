// Una linea del detalle de la factura de compra. costo, canfac, canrec,
// despor y descto son los que carga el usuario; tasa/gravada/iva/total
// los calcula el propio formulario en vivo (ver form-compra) y son los
// que se mandan al backend. Igual que en Producto, el backend NUNCA
// confia en com_total/com_grav10/etc. del payload: los recalcula el
// mismo desde el detalle. Pero gravada/iva POR LINEA si viajan desde
// aca, porque son el insumo con el que el backend arma esos totales.
export interface CompraDetalleLinea {
  prd_id: number | null;
  tiv_id: number | null;
  descri: string;

  costo: number | null;    // precio de costo UNITARIO, sin IVA
  canfac: number | null;   // cantidad facturada
  canrec: number | null;   // cantidad recibida (< canfac = entrega parcial)
  despor: number | null;   // % descuento (opcional)
  descto: number | null;   // monto descuento (opcional)

  // calculados en pantalla; se envian tal cual al backend
  tasa: number;
  gravada: number;   // TOTAL de la linea (costo x canfac, con descuento), no unitario
  iva: number;        // TOTAL de la linea
  total: number;      // gravada + iva, solo para mostrar

  // solo UI, no se envia
  codigo?: string;
}

export interface CompraCab {
  com_id?: number;
  com_proid: number | null;
  com_depid: number | null;
  com_sucid: number | null;
  com_tcoid: number | null;

  com_timbra?: string | null;
  com_timbvn?: string | null;
  com_nrodoc: string;
  com_fchaem: string;
  com_nrorem?: string | null;

  com_condic: number; // 0 contado, 1 credito
  com_plzdia?: number | null;
  com_cuotas?: number | null;
  com_interv?: number | null;
  com_fchvto?: string | null;

  com_obs?: string | null;

  detalle: CompraDetalleLinea[];

  // Solo lectura: los calcula el backend, nunca se mandan en create().
  com_proruc?: string;
  com_proraz?: string;
  com_exenta?: number;
  com_grav5?: number;
  com_grav10?: number;
  com_iva5?: number;
  com_iva10?: number;
  com_total?: number;
  com_estfac?: number;
  com_creado?: string;

  proveedor?: { pro_id: number; pro_razon: string; pro_ruc: string };
  deposito?: { dpo_id: number; dpo_nombre: string };
  sucursal?: { suc_id: number; suc_nombre: string };
  tipo_comprobante?: { tco_id: number; tco_descri: string };
}

export interface ProveedorOption { pro_id: number; pro_razon: string; pro_ruc: string; }
export interface DepositoOption { dpo_id: number; dpo_nombre: string; dpo_sucid: number; suc_nombre: string; }
export interface TipoComprobanteOption { tco_id: number; tco_descri: string; tco_reqtim: number; }
export interface TipoIvaOption { tiv_id: number; tiv_descri: string; tiv_tasa: number; }

export interface CompraConfig {
  proveedores: ProveedorOption[];
  depositos: DepositoOption[];
  tipos_comprobante: TipoComprobanteOption[];
  tipos_iva: TipoIvaOption[];
}
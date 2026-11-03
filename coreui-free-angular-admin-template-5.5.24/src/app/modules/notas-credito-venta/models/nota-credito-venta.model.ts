/** Catálogo compartido con las NC de compra. mnc_afesto = 1 → mueve mercadería. */
export interface MotivoNotaCredito {
  mnc_id: number;
  mnc_codigo: string;
  mnc_descri: string;
  mnc_afesto: number;        // 1 = devolución física (en venta: ENTRADA de stock)
  mnc_canfij?: number;       // 1 = la cantidad es el saldo de unidades y no se tipea
  mnc_obsreq?: number;       // 1 = observación obligatoria
  mnc_destex?: number;       // 1 = el destino de la mercadería no tiene sugerencia
  mnc_dnit?: number | null;
  mnc_estado: number;
}

/** Una línea de la factura, con lo que todavía queda sin acreditar. */
export interface LineaFacturaNC {
  vnd_id: number;
  vnd_prdid: number | null;     // null = línea de envío
  vnd_codigo: string;
  vnd_descripcion: string;
  vnd_umedida: string;
  vnd_cantidad: number;
  vnd_precio: number;           // unitario CON IVA incluido
  vnd_tasa: number;
  vnd_valorventa: number;
  admite_decimales: boolean;    // unidad_medida.umd_decimal
  saldo_acreditable: number;    // unidades que todavía se pueden acreditar
  saldo_importe: number;        // importe que todavía se puede acreditar
}

/** Respuesta de /admin/facturas-venta/{id}/notas-credito/config */
export interface ConfigPorFactura {
  ven_id: number;
  ven_nrodoc: string;
  ven_fchaem: string;
  ven_estado: number;
  ven_razonsocial: string;
  ven_ruc: string | null;
  ven_ci: string | null;
  ven_total: number;
  ven_acreditado: number;
  motivos: MotivoNotaCredito[];
  depositos: DepositoOption[];
  lineas: LineaFacturaNC[];
}

/** Lo que el formulario ENVÍA. El importe y el número los resuelve el backend. */
export interface DetNotaCreditoVentaInput {
  vnd_id: number;
  cantidad: number;
  precio?: number;              // solo en motivos que NO mueven stock
}

export interface NotaCreditoVentaInput {
  ncv_venid: number;
  ncv_mncid: number;
  ncv_fecha: string;
  ncv_obs?: string | null;
  ncv_reingresa?: boolean;      // solo en motivos que mueven mercadería
  ncv_depid?: number | null;
  detalle: DetNotaCreditoVentaInput[];
}

/** Lo que el backend DEVUELVE. */
export interface DetNotaCreditoVenta {
  dnv_id: number;
  dnv_vndid: number;
  dnv_prdid: number | null;
  dnv_nroitem: number;
  dnv_codigo: string;
  dnv_descripcion: string;
  dnv_umedida: string;
  dnv_cantidad: number;
  dnv_precio: number;
  dnv_tasa: number;
  dnv_valorventa: number;
  dnv_iva: number;
  dnv_canrei: number;           // cuánto reingresó realmente al depósito
}

export interface FacturaAsociada {
  ven_id: number;
  ven_nrodoc: string;
  ven_fchaem: string;
  ven_total: number;
}

/**
 * Estado de la declaración electrónica (SIFEN) de la NC. null en la NC =
 * se emitió sin NC electrónica (antes de activar la integración).
 */
export interface NotaCreditoVentaFe {
  estado: number;                 // 1 Pendiente, 2 Enviada, 3 Rechazada
  error: string | null;           // código corto del último error (para depurar)
  mensaje: string | null;
  aviso: string | null;
  cdc: string | null;             // 44 dígitos
  ambiente: string | null;        // simulado | test | prod
  qr: string | null;
  aprobado: string | null;
  intentos: number;
  ultimo_intento: string | null;
  tiene_xml: boolean;
  tiene_pdf: boolean;
  bloquea_anulacion: boolean;     // aprobada o en proceso: no se anula en el sistema
  correo_enviado: boolean | null;
  correo_detalle: string | null;
}

export interface FeEstadoOption {
  valor: number | string;         // 1 | 2 | 3 | 'sin'
  descripcion: string;
}

export interface NotaCreditoVenta {
  ncv_id: number;
  ncv_venid: number;
  ncv_cliid: number;
  ncv_sucid: number;
  ncv_depid: number | null;

  ncv_nrodoc: string;           // 001-001-0000045 (lo arma el backend)
  ncv_establec: string;
  ncv_puntoexp: string;
  ncv_numero: number;
  ncv_timbrado: string;
  ncv_timb_inicio: string;
  ncv_timb_venc: string;
  ncv_emp_ruc: string;
  ncv_emp_razon: string;
  ncv_fecha: string;

  ncv_razonsocial: string;
  ncv_ruc: string | null;
  ncv_ci: string | null;

  ncv_mncid: number;
  motivo?: MotivoNotaCredito | null;

  ncv_exenta: number;
  ncv_grav5: number;
  ncv_grav10: number;
  ncv_subtotal: number;
  ncv_total: number;
  ncv_iva5: number;
  ncv_iva10: number;
  ncv_totiva: number;

  ncv_estado: number;           // 1 Emitida, 9 Anulada
  ncv_obs: string | null;
  ncv_creado: string;
  ncv_anulado: string | null;
  ncv_motanu: string | null;

  factura?: FacturaAsociada;
  suc_nombre?: string;
  dpo_nombre?: string | null;
  detalle?: DetNotaCreditoVenta[];

  // En NotaCreditoVenta, junto a "factura?: FacturaAsociada;"
  fe?: NotaCreditoVentaFe | null;
}

export interface EstadoOption {
  valor: number;
  descripcion: string;
}

export interface SucursalOption {
  suc_id: number;
  suc_nombre: string;
}

export interface NotaCreditoVentaConfig {
  sucursales: SucursalOption[];
  motivos: MotivoNotaCredito[];
  estados: EstadoOption[];
  fe_estados?: FeEstadoOption[];
}

export interface NotaCreditoVentaFiltros {
  search?: string;
  estado?: string;
  motivo?: string;
  sucursal?: string;
  desde?: string;
  hasta?: string;
  fe_estado?: string;
}

export interface DepositoOption {
  dpo_id: number;
  dpo_nombre: string;
  dpo_princi: number;
}
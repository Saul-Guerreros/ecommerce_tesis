export interface MotivoRechazoCuota {
  motivo: string | null;
  detalle: string | null;
}

/** Un método dentro de la declaración. Con pago dividido hay varios. */
export interface MedioPagoCuota {
  cpm_id: number;
  orden: number;
  metodo: string | null;
  monto: number;
  estado: string | null;
  estado_descri: string | null;
  tiene_comprobante: boolean;
  puede_reemplazar: boolean;
  motivo_rechazo: MotivoRechazoCuota | null;
}

export interface PagoCuotaDeclarado {
  cpa_id: number;
  estado: string;
  estado_descri: string;
  /** Total de la declaración, que puede cubrir varias cuotas. */
  monto_total: number;
  fecha: string | null;
  medios: MedioPagoCuota[];
}

/** Una línea de la columna "Productos": qué paga esta cuota y por cuánto. */
export interface ProductoCuota {
  descripcion: string;
  cantidad: number;
  importe: number;
}

export interface MiCuota {
  cco_id: number;
  numero: number;
  vencimiento: string;
  importe: number;
  composicion: string | null;
  productos: ProductoCuota[];
  estado: 'PENDIENTE' | 'COBRADA' | 'ANULADA';
  vencida: boolean;
  fecha_cobro: string | null;
  pago: PagoCuotaDeclarado | null;
  /** Lo decide el backend. */
  puede_pagar: boolean;
}

/** Tarjeta "Resumen de Compra" de la sección Cuotas. Sale del pedido. */
export interface ResumenCompraCuotas {
  subtotal: number;
  impuestos: number;
  costo_envio: number;
  total: number;
  es_retiro: boolean;
}

/** Tarjeta "Facturación": sólo nombre y documento, más el PDF. */
export interface FacturacionCuotas {
  razon_social: string;
  tipo_documento: string;
  nro_documento: string;
}

export interface FacturaCuotas {
  numero: string;
  fecha: string;
  total: number;
  /** Habilita [Ver factura]: el PDF existe y es el que recibió el cliente. */
  pdf_disponible: boolean;
}

export interface MisCuotasRespuesta {
  /** false mientras el pedido no tenga factura a crédito: solo el aviso. */
  facturado: boolean;
  pedido: { numero: string; creado: string | null };
  resumen_compra: ResumenCompraCuotas;
  facturacion: FacturacionCuotas | null;
  factura: FacturaCuotas | null;
  cuotas: MiCuota[];
  metodos?: MetodoPagoCuota[];
  resumen: {
    total_cuotas: number;
    cobradas: number;
    saldo: number;
    proximo_venc: string | null;
  } | null;
}

export interface MetodoPagoCuota {
  mpa_id: number;
  mpa_codigo: string;
  mpa_descri: string;
  mpa_requcom: boolean;
}

/** Una fila del listado /mis-cuotas: una compra a crédito ya facturada. */
export interface CompraConCuotas {
  ped_numero: string;
  factura_numero: string;
  factura_fecha: string | null;
  total: number;
  total_cuotas: number;
  cobradas: number;
  saldo: number;
  proximo_venc: string | null;
  vencidas: number;
}
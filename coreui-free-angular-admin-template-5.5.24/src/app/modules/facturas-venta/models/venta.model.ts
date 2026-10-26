export interface VentaDetalle {
  vnd_id: number;
  vnd_prdid: number | null;      // null = linea conceptual (costo de envio)
  vnd_nroitem: number;
  vnd_codigo: string;
  vnd_descripcion: string;
  vnd_umedida: string;
  vnd_nroserie?: string | null;
  vnd_cantidad: number;
  vnd_precio: number;            // unitario CON IVA incluido
  vnd_descuento: number;
  vnd_tasa: number;              // 0 | 5 | 10
  vnd_valorventa: number;
  vnd_iva: number;
}

/**
 * Estado de la declaracion electronica (SIFEN) de la factura. null en la
 * Venta = se emitio sin factura electronica (antes de activar la integracion).
 */
export interface VentaFe {
  estado: number;                 // 1 Pendiente, 2 Enviada, 3 Rechazada
  error: string | null;           // codigo corto del ultimo error (para depurar)
  mensaje: string | null;
  aviso: string | null;
  cdc: string | null;             // 44 digitos
  ambiente: string | null;        // simulado | test | prod
  qr: string | null;
  aprobado: string | null;
  intentos: number;
  ultimo_intento: string | null;
  tiene_xml: boolean;
  tiene_pdf: boolean;
  bloquea_anulacion: boolean;     // aprobada o en proceso: no se anula, se corrige con nota de credito
  correo_enviado: boolean | null;   // null = no se intentó mandar por correo
  correo_detalle: string | null;    // destinatario si salió; motivo si no salió
}

export interface Venta {
  ven_id: number;
  ven_pedid: number | null;
  ven_cliid: number;
  ven_sucid: number;

  // Documento
  ven_nrodoc: string;            // 001-001-0000123 (lo arma el backend)
  ven_establec: string;
  ven_puntoexp: string;
  ven_numero: number;
  ven_timbrado: string;
  ven_timb_inicio: string;
  ven_timb_venc: string;
  ven_emp_ruc: string;     // RUC de la empresa que vende (snapshot)
  ven_emp_razon: string;   // razon social de la empresa que vende (snapshot)
  ven_fchaem: string;

  // Adquirente (snapshot al momento de emitir)
  ven_razonsocial: string;
  ven_ruc: string | null;
  ven_ci: string | null;
  ven_direccion: string | null;
  ven_telefono: string | null;
  ven_email: string | null;

  ven_condicion: number;         // 1 Contado, 2 Credito
  ven_cant_cuotas: number | null;

  ven_exenta: number;
  ven_grav5: number;
  ven_grav10: number;
  ven_subtotal: number;
  ven_total: number;
  ven_iva5: number;
  ven_iva10: number;
  ven_totiva: number;

  ven_estado: number;            // 1 Emitida, 2 Cobrada, 9 Anulada
  ven_obs: string | null;
  ven_creado: string;
  ven_anulado: string | null;
  ven_motanu: string | null;

  suc_nombre?: string;
  caj_descri?: string;
  ped_numero?: string;

  detalle?: VentaDetalle[];

  fe?: VentaFe | null;
}

export interface EstadoOption {
  valor: number;
  descripcion: string;
}

export interface FeEstadoOption {
  valor: number | string;         // 1 | 2 | 3 | 'sin'
  descripcion: string;
}

export interface SucursalOption {
  suc_id: number;
  suc_nombre: string;
}

export interface VentaConfig {
  sucursales: SucursalOption[];
  estados: EstadoOption[];
  fe_estados?: FeEstadoOption[];
}

export interface VentaFiltros {
  search?: string;
  estado?: string;
  fe_estado?: string;
  desde?: string;
  hasta?: string;
}
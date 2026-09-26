export interface CuentaPorPagar {
  com_id: number;
  com_proid: number;
  com_nrodoc: string;
  com_proraz: string;
  com_fchaem: string;
  com_total: number;
  cuo_total: number;
  cuo_pendientes: number;
  cuo_saldo: number;
  cuo_provencim: string | null;
}

export interface PagoCompra {
  pgo_id: number;
  pgo_cuoid: number;
  pgo_fecha: string;
  pgo_formapa: string;
  pgo_referencia: string | null;
  pgo_observ: string | null;
  pgo_importe: number;
  pgo_estado: number; // 0 Anulado, 1 Activo
  pgo_motanu: string | null;
}

export interface CuotaPendiente {
  cuo_id: number;
  cuo_comid: number;
  cuo_nrocuo: number;
  cuo_venci: string;
  cuo_importe: number;
  cuo_saldo: number;
  cuo_estado: number; // 0 pendiente, 1 pagada
  cuo_fecpago: string | null;
  com_nrodoc?: string;
  com_proid?: number;
  pago: PagoCompra | null;
}

export interface ProveedorOption {
  pro_id: number;
  pro_razon: string;
}

export interface NuevoPagoCompra {
  cuo_id: number;
  pgo_fecha: string;
  pgo_formapa: string;
  pgo_referencia?: string | null;
  pgo_observ?: string | null;
}
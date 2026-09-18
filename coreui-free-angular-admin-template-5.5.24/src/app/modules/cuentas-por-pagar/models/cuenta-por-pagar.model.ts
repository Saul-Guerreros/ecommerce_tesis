export interface CuentaPorPagar {
  com_id: number;
  com_nrodoc: string;
  com_proraz: string;
  com_fchaem: string;
  com_total: number;
  cuo_total: number;
  cuo_pendientes: number;
  cuo_saldo: number;
  cuo_provencim: string | null;
}

export interface CuotaPendiente {
  cuo_id: number;
  cuo_nrocuo: number;
  cuo_venci: string;
  cuo_importe: number;
  cuo_estado: number;
  cuo_fecpago: string | null;
}

export interface ProveedorOption {
  pro_id: number;
  pro_razon: string;
}

export interface PagoCompra {
  pgo_id: number;
  pgo_cuoid: number;
  pgo_fecha: string;
  pgo_importe: number;
  pgo_formapa: string;
  pgo_observ: string | null;
}

export interface CuotaPendiente {
  cuo_id: number;
  cuo_nrocuo: number;
  cuo_venci: string;
  cuo_importe: number;
  cuo_saldo: number;          // <-- nuevo: importe - pagos ya registrados
  cuo_estado: number;
  cuo_fecpago: string | null;
  pagos: PagoCompra[];        // <-- nuevo
}
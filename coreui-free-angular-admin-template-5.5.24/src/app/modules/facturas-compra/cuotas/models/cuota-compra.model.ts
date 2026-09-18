export interface CuotaCompra {
  cuo_id: number;
  cuo_comid: number;
  cuo_nrocuo: number;
  cuo_venci: string;
  cuo_importe: number;
  cuo_estado: number;       // 0 pendiente, 1 pagada
  cuo_fecpago: string | null;
}
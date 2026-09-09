export interface AjusteDetalle {
  aid_id?: number;
  prd_id: number | null;
  prd_codinv?: string;
  prd_descri?: string;
  cantsis?: number | null;   // solo informativo, lo calcula el backend
  cantfis: number | null;
  costo?: number | null;      // requerido solo si termina siendo sobrante
  obs?: string | null;
  diferencia?: number;
}

export interface AjusteInventario {
  aji_id?: number;
  aji_tpaid: number | null;
  aji_depid: number | null;
  aji_fecha: string;
  aji_referencia?: string | null;
  aji_obs?: string | null;
  aji_estado?: number;    // 0 Borrador, 1 Confirmado, 2 Anulado
  aji_creado?: string;
  aji_confir?: string | null;
  aji_anulado?: string | null;

  tpa_descri?: string;
  tpa_natura?: string;    // 'P' Positivo / 'N' Negativo
  dpo_nombre?: string;

  detalle?: AjusteDetalle[];
}

export interface TipoAjusteOption {
  tpa_id: number;
  tpa_codig: string;
  tpa_descri: string;
  tpa_natura: string;
  tpa_reqcom: number;
}

export interface DepositoOption {
  dpo_id: number;
  dpo_nombre: string;
  dpo_sucid: number;
  suc_nombre: string;
}

export interface AjusteConfig {
  tipos: TipoAjusteOption[];
  depositos: DepositoOption[];
}
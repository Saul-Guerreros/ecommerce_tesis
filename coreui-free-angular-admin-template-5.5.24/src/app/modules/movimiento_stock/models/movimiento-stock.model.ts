export interface MovimientoStock {
  mst_id: number;
  mst_fecha: string;
  mst_tipo: string;       // 'E' | 'S'
  mst_cantid: number;
  mst_cosuni: number;
  mst_salcan: number;
  mst_salcos: number;
  mst_obs?: string | null;
  origen: string;          // 'Compra' | 'Ajuste' | '—'
  prd_codinv?: string;
  prd_descri?: string;
  dpo_nombre?: string;
}

export interface ProductoOption {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
}

export interface DepositoOption {
  dpo_id: number;
  dpo_nombre: string;
  suc_nombre: string;
}

export interface KardexConfig {
  productos: ProductoOption[];
  depositos: DepositoOption[];
}
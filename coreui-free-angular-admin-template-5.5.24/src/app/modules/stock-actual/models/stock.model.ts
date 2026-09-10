export interface StockItem {
  sto_id: number;
  sto_cantid: number;
  sto_reserv: number;
  sto_cosprm: number;
  sto_actual: string;
  disponible: number;
  valorizado: number;
  bajo_minimo?: boolean;

  prd_codinv?: string;
  prd_descri?: string;
  prd_stkmin?: number;
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

export interface MarcaOption {
  mar_id: number;
  mar_descri: string;
}

export interface StockConfig {
  productos: ProductoOption[];
  depositos: DepositoOption[];
  marcas: MarcaOption[];
}
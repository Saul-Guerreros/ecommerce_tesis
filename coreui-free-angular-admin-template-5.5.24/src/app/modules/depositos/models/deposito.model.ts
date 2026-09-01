export interface Deposito {
  dpo_id?: number;
  dpo_sucid: number | null;
  dpo_codigo: string;
  dpo_nombre: string;
  dpo_descri?: string | null;
  dpo_princi?: number;
  dpo_estado?: number;
  dpo_creado?: string;

  // calculados por el backend
  suc_nombre?: string;
  emp_razonsocial?: string;
  usos_count?: number;
}

export interface SucursalOption {
  suc_id: number;
  suc_nombre: string;
  emp_razonsocial: string;
}
export interface Empresa {
  emp_id?: number;
  emp_razonsocial: string;
  emp_ruc: string;
  emp_telef?: string | null;
  emp_email?: string | null;
  emp_actividad?: string | null;
  emp_estado?: number;          // 0 = inactivo, 1 = activo
  emp_creado?: string;
  sucursales_count?: number;    // lo agrega el backend en el index()
}

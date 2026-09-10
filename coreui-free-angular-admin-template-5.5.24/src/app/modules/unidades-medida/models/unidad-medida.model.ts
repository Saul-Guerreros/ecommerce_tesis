export interface UnidadMedida {
  umd_id?: number;
  umd_codigo: string;
  umd_nombre: string;
  umd_decimal?: number;   // 0 = cantidades enteras, 1 = admite decimales
  umd_estado?: number;
  usos_count?: number;
}
export interface Producto {
  prd_id?: number;
  prd_marid: number | null;
  prd_tivid: number | null;
  prd_umdid: number | null;
  prd_codinv: string;
  prd_prevta: number | null;
  prd_margen?: number | null;   // % de referencia para sugerir precio (opcional)
  prd_descri: string;
  prd_stkmin: number | null;
  prd_afestk?: number;    // 1 = afecta stock al venderse, 0 = no (ej. servicios)
  prd_estado?: number;
  prd_creado?: string;

  // calculados por el backend
  mar_descri?: string;
  tiv_descri?: string;
  tiv_tasa?: number;
  umd_codigo?: string;
  umd_nombre?: string;

  /**
   * Solo lectura. NO se envia en create()/update(): el backend lo ignora
   * igual aunque llegue. Suma sto_cantid de TODOS los depositos (el
   * formulario no distingue por deposito). Se actualiza automaticamente
   * cada vez que se confirma o anula una compra.
   */
  stock_actual?: number;

  /**
   * Costo promedio ponderado GLOBAL (agregando todos los depositos) x
   * (1 + prd_margen/100). null si el producto no tiene margen definido o
   * no tiene stock todavia. Nunca sobreescribe prd_prevta solo — es
   * puramente informativo en este formulario.
   */
  precio_sugerido?: number | null;

  usos_count?: number;
}

export interface MarcaOption {
  mar_id: number;
  mar_descri: string;
}

export interface TipoIvaOption {
  tiv_id: number;
  tiv_descri: string;
  tiv_tasa: number;
}

export interface UnidadMedidaOption {
  umd_id: number;
  umd_codigo: string;
  umd_nombre: string;
  umd_decimal: number;
}

export interface ProductoConfig {
  marcas: MarcaOption[];
  tipos_iva: TipoIvaOption[];
  unidades: UnidadMedidaOption[];
}

// Version liviana de Producto para el buscador de compras/ventas.
// Viene de GET /productos/select.
export interface ProductoSelect {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  prd_prevta: number | null;
  prd_tivid: number;
  tiv_tasa: number;
}
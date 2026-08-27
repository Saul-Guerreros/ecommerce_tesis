export interface CuponConfigItem {
  id: number;
  title?: string;   // productos (prd_titulo)
  name?: string;    // categorias (cat_nombre) / marcas (mar_descri)
  id_aux?: number;  // presente cuando viene de "show" (id de la fila pivote)
}

export interface CuponConfig {
  products: CuponConfigItem[];
  categories: CuponConfigItem[];
  brands: CuponConfigItem[];
}

export interface Cupon {
  id?: number;
  code: string;
  type_discount: number;   // 1 = porcentaje, 2 = monto fijo
  discount: number;
  type_count: number;      // 1 = ilimitado, 2 = limitado
  num_use: number | null;
  type_cupone: number;     // 1 = productos, 2 = categorias, 3 = marcas
  state: number;           // 1 = activo, 2 = inactivo
  created_at?: string;
  products?: CuponConfigItem[];
  categories?: CuponConfigItem[];
  brands?: CuponConfigItem[];
}

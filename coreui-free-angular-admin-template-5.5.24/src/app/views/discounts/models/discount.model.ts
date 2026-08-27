export interface DiscountConfigItem {
  id: number;
  title?: string;   // productos (prd_titulo)
  name?: string;    // categorias (cat_nombre) / marcas (mar_descri)
  id_aux?: number;  // presente cuando viene de "show" (id de la fila pivote)
}

export interface Discount {
  id?: number;
  code?: string;            // se autogenera en el backend
  type_discount: number;    // 1 = porcentaje, 2 = monto fijo
  discount: number;
  start_date: string;       // yyyy-MM-dd
  end_date: string;         // yyyy-MM-dd
  discount_type: number;    // 1 = productos, 2 = categorias, 3 = marcas
  type_campaing: number;    // 1 = normal, 2 = flash, 3 = link
  state: number;            // 1 = activo, 2 = inactivo
  created_at?: string;
  products?: DiscountConfigItem[];
  categories?: DiscountConfigItem[];
  brands?: DiscountConfigItem[];
}

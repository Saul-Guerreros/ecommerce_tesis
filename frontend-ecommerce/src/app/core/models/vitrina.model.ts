/**
 * Shape de cada producto tal como lo arma
 * VitrinaController::formatearProducto() en el backend.
 */
export interface ProductoVitrina {
  pub_id: number;
  prd_id: number;
  sku: string;
  titulo: string;
  slug: string | null;
  categoria: string | null;
  marca: string | null;
  imagen: string | null; // URL absoluta ya armada por el backend (asset('storage/...'))
  precio_original: number;
  precio_final: number;
  descuento_pct: number | null;
  oferta_fin: string | null;
  es_flash: boolean;
  rating: number | null;
  // Datos reales de inventario (Producto::prd_stock/prd_afestk). Opcionales
  // porque endpoints viejos que todavía no se actualizaron podrían no
  // mandarlos; el frontend debe tratarlos como "desconocido" en ese caso.
  stock?: number | null;
  controla_stock?: boolean;
}

export interface VitrinaHomeResponse {
  data: {
    nuevos: ProductoVitrina[];
    destacados: ProductoVitrina[];
    trending: ProductoVitrina[];
    mas_vendidos: ProductoVitrina[];
    en_oferta: ProductoVitrina[];
    oferta_del_dia: ProductoVitrina[];
  };
}

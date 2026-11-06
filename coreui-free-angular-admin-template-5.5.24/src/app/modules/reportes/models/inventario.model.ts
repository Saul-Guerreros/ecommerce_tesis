/** Respuestas de /api/admin/reportes/inventario/* */

export type VistaInventario = 'valorizado' | 'critico' | 'sin-movimiento' | 'margen';

export interface FiltrosInventarioExtra {
  categoria_id?: number | null;
  marca_id?: number | null;
  dias?: number;
}

interface ProductoBase {
  id: number;
  codigo: string;
  producto: string;
  categoria: string;
  marca: string;
}

// ---------- valorizado ----------
export interface StockValorizado extends ProductoBase {
  deposito: string;
  cantidad: number;
  costo: number;
  valor: number;
}
export interface ValorDeposito {
  id: number;
  deposito: string;
  productos: number;
  unidades: number;
  valor: number;
  participacion: number | null;
}
export interface TotalesValorizado {
  productos: number;
  unidades: number;
  valor: number;
  por_deposito: ValorDeposito[];
}

// ---------- crítico ----------
export interface StockCritico extends ProductoBase {
  deposito: string;
  existencia: number;
  reservado: number;
  disponible: number;
  minimo: number;
  reponer: number;
  costo_reposicion: number;
  nivel: 'agotado' | 'critico';
}
export interface TotalesCritico {
  productos: number;
  agotados: number;
  criticos: number;
  costo_reposicion: number;
}

// ---------- sin movimiento ----------
export interface StockSinMovimiento extends ProductoBase {
  deposito: string;
  cantidad: number;
  valor: number;
  ultima_salida: string | null;
  ultima_entrada: string | null;
  dias: number | null;
}
export interface TotalesSinMovimiento {
  productos: number;
  unidades: number;
  valor: number;
  nunca: number;
  dias_umbral: number;
}

// ---------- margen ----------
export interface MargenProducto extends ProductoBase {
  existencia: number;
  costo: number;
  precio: number;
  precio_sin_iva: number;
  ganancia_unitaria: number;
  margen_pct: number | null;
  markup_real: number;
  markup_config: number;
  desactualizado: boolean;
}
export interface TotalesMargen {
  productos: number;
  margen_promedio: number | null;
  negativos: number;
  desactualizados: number;
  markup_general: number;
}
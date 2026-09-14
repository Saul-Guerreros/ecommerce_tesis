// Una fila de la Lista de Precios. costo_promedio, prd_margen y prd_prevta
// pueden venir en null: significa que el producto todavia no tuvo ninguna
// compra confirmada (sin stock, sin CPP calculado todavia).
export interface Precio {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
   tiv_tasa: number;              // NUEVO — tasa de IVA del producto 
  costo_promedio: number | null;
  prd_margen: number | null;
  prd_prevta: number | null;
}
export type OrigenMargen = 'general' | 'individual';

export interface PrecioTipo {
  margen: number;
  origen: OrigenMargen;
  precio: number | null;
}

export interface UmbralMayorista {
  cantidad: number;
  origen: OrigenMargen;
}

// Una fila de la Lista de Precios. costo_promedio puede venir en null:
// significa que el producto todavia no tuvo ninguna compra confirmada
// (sin stock, sin CPP calculado todavia).
export interface Precio {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  tiv_tasa: number;
  costo_promedio: number | null;
  minorista: PrecioTipo;
  mayorista: PrecioTipo;
  umbral_mayorista: UmbralMayorista;
}

// Payload de PATCH /admin/precios/{id}. Los tres campos SIEMPRE se
// envian, aunque sean null (null = "usar el general") -- el backend
// solo pisa el valor si la clave esta presente en el body.
export interface ActualizarPrecioPayload {
  prd_marmin: number | null;
  prd_marmaj: number | null;
  prd_canminmaj: number | null;
}
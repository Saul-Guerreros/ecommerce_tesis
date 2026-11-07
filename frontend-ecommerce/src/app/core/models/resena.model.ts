export type OrdenResenas = 'recientes' | 'mejor' | 'peor';

export interface RespuestaTienda {
  texto: string;
  fecha: string | null;
}

export interface ResenaPublica {
  id: number;
  autor: string;
  calificacion: number;
  titulo: string | null;
  comentario: string;
  producto_comprado: string | null;
  editada: boolean;
  fecha: string | null;
  respuesta: RespuestaTienda | null;
}

/** La reseña propia: incluye su estado de moderación. */
export interface MiResena extends ResenaPublica {
  visible: boolean;
  motivo_oculta: string | null;
}

export interface ResumenResenas {
  promedio: number | null;
  total: number;
  /** Claves '5'..'1' con la cantidad de reseñas de cada estrella. */
  distribucion: Record<string, number>;
}

export interface ResenasPaginadas {
  data: ResenaPublica[];
  meta: { current_page: number; last_page: number; total: number };
  resumen: ResumenResenas;
}

export interface EstadoMiResena {
  puede_resenar: boolean;
  motivo: string | null;
  resena: MiResena | null;
}

export interface ResenaPayload {
  res_califi: number;
  res_titulo: string | null;
  res_coment: string;
}

export interface ResenaGuardadaResponse {
  message: string;
  data: MiResena;
  resumen: ResumenResenas;
}

/** Fila del bloque "Calificá tus productos" de Mis Pedidos. */
export interface ProductoPorResenar {
  prd_id: number;
  nombre: string;
  imagen: string | null;
  /** null = el producto ya no está publicado en la tienda. */
  slug: string | null;
  resenada: boolean;
  calificacion: number | null;
}

/** Testimonio del Home: reseña pública + slug del producto para enlazarlo. */
export interface ResenaDestacada extends ResenaPublica {
  slug: string | null;
}
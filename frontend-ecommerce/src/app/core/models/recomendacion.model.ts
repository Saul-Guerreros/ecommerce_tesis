import { ProductoVitrina } from './vitrina.model';

/** Cómo se generó la lista (sirve para el subtítulo y para depurar). */
export type EstrategiaRecomendacion =
  | 'personalizado' | 'similares' | 'categoria' | 'tendencias' | 'novedades';

export interface RecomendacionResponse {
  data: ProductoVitrina[];
  meta: { estrategia: EstrategiaRecomendacion; total: number };
}

export type TipoRecomendacion = 'para-ti' | 'producto' | 'tendencias';
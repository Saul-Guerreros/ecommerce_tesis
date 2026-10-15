import { EspecificacionDetalle } from './producto-detalle.model';
import { ProductoVitrina } from './vitrina.model';

/** Lo único que se guarda en el navegador por cada producto a comparar. */
export interface ItemComparador {
  slug: string;
  titulo: string;
  imagen: string | null;
  categoria: string | null;
}

/** Resultado de agregar/quitar desde un botón "Comparar". */
export interface ResultadoComparador {
  estado: 'agregado' | 'quitado' | 'rechazado';
  motivo?: 'limite' | 'categoria';
  categoriaActual?: string | null;
}

/** Respuesta de GET /api/tienda/productos/comparar?slugs=a,b,c */
export interface ComparacionResponse {
  data: {
    productos: {
      producto: ProductoVitrina;
      especificaciones: EspecificacionDetalle[];
    }[];
  };
}

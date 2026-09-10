import { ProductoVitrina } from './vitrina.model';

/**
 * Fila de la tabla "Especificaciones" del detalle (esp_valibr / esp_valnum /
 * propiedad, ya resueltos a un único `valor` por VitrinaController::detalle()).
 */
export interface EspecificacionDetalle {
  atributo: string | null;
  valor: string | number | null;
}

/**
 * Otro producto (color) publicado dentro del mismo grupo padre/hijos.
 */
export interface ColorDisponible {
  slug: string | null;
  titulo: string;
  imagen: string | null;
}

/**
 * Shape de "producto" dentro de /api/tienda/producto/{slug}: mismo shape
 * que ProductoVitrina (formatearProducto), sin campos extra propios.
 */
export type ProductoDetalle = ProductoVitrina;

export interface ProductoDetalleResponse {
  data: {
    producto: ProductoDetalle;
    // pub_descwb tal cual se cargó en el panel admin. null si la ficha
    // todavía no tiene descripción larga cargada.
    descripcion: string | null;
    // URLs absolutas ya armadas por el backend (asset('storage/...')),
    // ordenadas por img_orden. Puede venir vacío si no se cargó ninguna foto.
    galeria: string[];
    especificaciones: EspecificacionDetalle[];
    colores_disponibles: ColorDisponible[];
    relacionados: ProductoVitrina[];
  };
}

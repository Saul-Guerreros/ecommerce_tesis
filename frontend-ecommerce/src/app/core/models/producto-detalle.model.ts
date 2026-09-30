import { CuotaOpcion, ProductoVitrina } from './vitrina.model';

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

/**
 * Un tramo de precio (minorista o mayorista) con sus cuotas YA calculadas
 * por el backend sobre el precio UNITARIO de ese tramo.
 */
export interface TramoPrecio {
  precio_unitario: number;
  cuotas: CuotaOpcion[];
}

export interface TramoMayorista extends TramoPrecio {
  /** Cantidad a partir de la cual rige este tramo. */
  cantidad_minima: number;
}

/**
 * Opciones de compra del detalle. El frontend solo ELIGE el tramo según la
 * cantidad; no calcula precios, recargos ni cuotas.
 *
 *  - minorista = null  -> producto solo mayorista.
 *  - mayorista = null  -> producto sin venta mayorista.
 *  - ambos null        -> producto sin precio de venta configurado.
 */
export interface OpcionesCompra {
  /** Cantidad inicial y piso de ambos controles de unidades. */
  cantidad_minima: number;
  solo_mayorista: boolean;
  minorista: TramoPrecio | null;
  mayorista: TramoMayorista | null;
}

/**
 * Avisos de stock según lo configurado por el administrador (Configuración
 * de la Tienda). El backend ya evalúa `poco_stock`; el frontend solo lo
 * muestra y compara la cantidad elegida contra el stock para el máximo.
 */
export interface StockAvisos {
  /** Aviso "Solo quedan X" activo y stock en o por debajo del umbral. */
  poco_stock: boolean;
  /** Unidades disponibles; null si el producto no controla stock. */
  unidades: number | null;
}

export interface ProductoDetalleResponse {
  data: {
    producto: ProductoDetalle;
    compra: OpcionesCompra;
    stock_avisos: StockAvisos;
    // pub_descwb tal cual se cargó en el panel admin. null si la ficha
    // todavía no tiene descripción larga cargada.
    descripcion: string | null;
    // URLs absolutas ya armadas por el backend (asset('storage/...')),
    // ordenadas por img_orden. Puede venir vacío si no se cargó ninguna foto.
    galeria: string[];
    especificaciones: EspecificacionDetalle[];
    colores_disponibles: ColorDisponible[];
    relacionados: ProductoVitrina[];
    // Nombres de sucursales con stock disponible (>0) de este producto ahora mismo.
    sucursales_pickup: string[];
  };
}

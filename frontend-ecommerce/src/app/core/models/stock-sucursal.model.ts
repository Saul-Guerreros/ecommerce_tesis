/**
 * Disponibilidad de un producto en una sucursal, tal como la publica
 * VitrinaController::stockSucursales().
 */
export interface StockSucursal {
  suc_id: number;
  nombre: string;
  direccion: string | null;
  horario: string | null;
  telefono: string | null;
  ciudad: string | null;
  /** Sucursal principal de la empresa: se lista primero. */
  principal: boolean;
  /** Habilitada para retiro de pedidos web. */
  retiro: boolean;
  /** Habilitada para envíos a domicilio. */
  delivery: boolean;
  /**
   * Unidades disponibles (cantidad - reservado) en esa sucursal.
   * null = el producto no controla stock; no se muestra ningún número.
   */
  existencia: number | null;
}

export interface StockSucursalesResponse {
  data: {
    producto: {
      titulo: string;
      controla_stock: boolean;
    };
    sucursales: StockSucursal[];
  };
}
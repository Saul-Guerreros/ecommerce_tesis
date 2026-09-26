export interface Producto {
  prd_id?: number;
  prd_marid: number | null;
  prd_tivid: number | null;
  prd_umdid: number | null;
  prd_codinv: string;
  prd_descri: string;
  prd_stkmin: number | null;
  prd_afestk?: number;    // 1 = afecta stock al venderse, 0 = no (ej. servicios)
  prd_estado?: number;
  prd_creado?: string;

  // calculados por el backend
  mar_descri?: string;
  tiv_descri?: string;
  tiv_tasa?: number;
  umd_codigo?: string;
  umd_nombre?: string;

  /**
   * Solo lectura. NO se envia en create()/update(): el backend lo ignora
   * igual aunque llegue. Suma sto_cantid de TODOS los depositos (el
   * formulario no distingue por deposito). Se actualiza automaticamente
   * cada vez que se confirma o anula una compra.
   */
  stock_actual?: number;

  /**
   * Costo promedio ponderado GLOBAL (agregando todos los depositos) x
   * margen minorista EFECTIVO (individual si lo tiene, general si no).
   * null si el producto no tiene stock todavia. Puramente informativo;
   * el precio real (minorista/mayorista) se gestiona en Gestión de
   * Precios, no en este formulario.
   */
  precio_sugerido?: number | null;

  usos_count?: number;

  /**
   * Solo para el alta (form-producto en modo creación). No son columnas
   * de "producto": el backend los usa aparte para crear, dentro de la
   * misma transacción, la primera fila en producto_equivalencia.
   * No se usan en modo edición (ahí sigue el CRUD de
   * app-equivalencia-producto tal como estaba).
   */
  tiene_equiv_compra?: boolean;
  peq_umdid?: number | null;
  peq_factor?: number | null;
}

export interface MarcaOption {
  mar_id: number;
  mar_descri: string;
}

export interface TipoIvaOption {
  tiv_id: number;
  tiv_descri: string;
  tiv_tasa: number;
}

export interface UnidadMedidaOption {
  umd_id: number;
  umd_codigo: string;
  umd_nombre: string;
  umd_decimal: number;
}

export interface ProductoConfig {
  marcas: MarcaOption[];
  tipos_iva: TipoIvaOption[];
  unidades: UnidadMedidaOption[];
}

// Version liviana de Producto para el buscador de compras/ventas.
// Viene de GET /productos/select.
export interface ProductoSelect {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  prd_pvtmin: number | null;
  prd_pvtmay: number | null;
  prd_tivid: number;
  tiv_tasa: number;
}

export interface ProductoEquivalencia {
  peq_id?: number;
  peq_prdid: number;
  peq_umdid: number;
  peq_factor: number;
  peq_estado?: number;      // 1 = activa · 0 = desactivada
  etiqueta?: string;        // "Caja (x10)"
  en_uso?: boolean;         // usada en compras: solo puede desactivarse
  umd_codigo?: string;
  umd_nombre?: string;
}

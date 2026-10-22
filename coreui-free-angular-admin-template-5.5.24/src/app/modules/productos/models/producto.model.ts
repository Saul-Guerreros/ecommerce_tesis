import { Atributo } from '../../../views/atributos/atributo.model';

export interface Producto {
  prd_id?: number;
  prd_marid: number | null;
  prd_tivid: number | null;
  prd_umdid: number | null;
  prd_codinv: string;
  prd_descri: string;
  prd_stkmin: number | null;
  prd_afestk?: number;    // 1 = afecta stock al venderse, 0 = no (ej. servicios)
  prd_repues?: number;    // 1 = repuesto de taller (Servicio Técnico): no se publica en la tienda
  prd_gardia?: number | null;   // días de garantía al vender; null = plazo general de la tienda, 0 = sin garantía
  prd_proid?: number | null;    // empresa (proveedor) que respalda la garantía; null = sin definir
  prd_estado?: number;
  prd_creado?: string;

  /**
   * Variación (Sistema B: un producto = una variante, ej. "Notebook X 8GB"
   * y "Notebook X 16GB" son dos filas de producto distintas). null = este
   * producto no es variante de nada. Un producto nunca tiene padre Y
   * variantes propias a la vez (ver ProductoRequest).
   */
  prd_padre_id?: number | null;
  padre?: VarianteResumen | null;
  variantes?: VarianteResumen[];

  /**
   * Solo viene en el listado (index()), vía withCount('variantes') -- no
   * carga las variantes en sí, solo cuántas tiene, para saber si mostrar
   * la flecha de expandir. Al expandir se pide el detalle completo con
   * getOne(), que sí trae `variantes` (el array de arriba).
   */
  variantes_count?: number;

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

// Fila liviana usada tanto para "padre" como para cada item de "variantes"
// dentro de un Producto.
export interface VarianteResumen {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  prd_estado?: number;
  pub_estado?: number | null; // 0 borrador / 1 publicado / 2 archivado / null sin publicar
  // Solo vienen cuando la variante se cargó como parte de GET /productos/{id}
  // (ver ProductoController::show), para pintar la fila expandida del
  // listado igual de completa que la del padre.
  mar_descri?: string | null;
  umd_codigo?: string | null;
  stock_actual?: number | null;
  usos_count?: number;
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

/** Empresa que puede respaldar la garantía de un producto (proveedor activo). */
export interface ProveedorOption {
  pro_id: number;
  pro_razon: string;
}

export interface ProductoConfig {
  marcas: MarcaOption[];
  proveedores?: ProveedorOption[];
  tipos_iva: TipoIvaOption[];
  unidades: UnidadMedidaOption[];
  // Todos los tipos de atributo, para el "Se diferencia por" de variantes.
  atributos_variante: Atributo[];
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
  heredada?: boolean; 
}

// Respuesta paginada estándar de Laravel (ResourceCollection + paginate)
export interface PaginacionMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface Paginado<T> {
  data: T[];
  meta: PaginacionMeta;
}
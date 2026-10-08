/** atb_tipo: 1 = Texto | 2 = Número | 3 = Seleccionable | 4 = Selección múltiple */
export type TipoAtributo = 1 | 2 | 3 | 4;

export interface CategoriaAtributo {
  cta_id?: number;
  cta_catid: number | null;
  cta_atbid: number | null;
  cta_orden: number | null;
  cta_obliga: number;          // 0 = opcional, 1 = obligatorio
  cta_creado?: string;

  // calculados por el backend
  atb_nombre?: string;
  atb_tipo?: TipoAtributo;
  atb_unidad?: string | null;
  atb_tipo_descri?: string;
  propiedades_count?: number;
  sin_opciones?: boolean;      // seleccionable sin propiedades cargadas
}

export interface SubcategoriaPlantilla {
  cat_id: number;
  cat_nombre: string;
  padre_nombre: string;
  total_atributos: number;
  total_obligatorios: number;
}

export interface AtributoOption {
  atb_id: number;
  atb_nombre: string;
  atb_tipo: TipoAtributo;
  atb_unidad?: string | null;
}

export interface CategoriaAtributoConfig {
  atributos: AtributoOption[];
}

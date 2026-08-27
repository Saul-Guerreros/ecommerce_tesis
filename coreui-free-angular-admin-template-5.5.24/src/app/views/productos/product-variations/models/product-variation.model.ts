export interface Propiedad {
  prp_id: number;
  prp_nombre: string;
}

export interface AtributoVariacion {
  atb_id: number;
  atb_nombre: string;
  propiedades: Propiedad[];
}

export interface ConfigVariacionesResponse {
  atributos_variaciones: AtributoVariacion[];
}

export interface PropiedadVariacion {
  prp_id: number;
  prp_nombre: string;
  atb_id: number;
  atb_nombre: string;
}

export interface Variacion {
  var_proid: number;
  var_prdid: number;
  var_preadi: number;
  var_stock: number;
  var_stkmin: number;
  var_estado: number;
  propiedades: PropiedadVariacion[];
}

export interface Atributo {
  atb_id: number;
  atb_nombre: string;
  atb_tipo: number; // 1: Texto | 2: Numero | 3: Seleccionable | 4: Seleccionable Multiple
}

export interface PropiedadEspecificacion {
  prp_id: number;
  prp_nombre: string;
}

export interface Especificacion {
  esp_id: number;
  esp_proid: number;
  esp_atbid: number;
  atributo: Atributo | null;
  esp_prpid: number;
  propiedad: PropiedadEspecificacion | null;
  esp_valibr: string | null;
  esp_valnum: number | null;
}

export interface ApiMessageResponse {
  message: number; // 200 = ok, 403 = error de validación de negocio
  message_text?: string;
}

export interface CarritoPropiedad {
  atb_nombre: string;
  prp_nombre: string;
}

export interface CarritoVariacion {
  var_proid: number;
  var_preadi: number;
  propiedades: CarritoPropiedad[];
}

export interface CarritoPublicacionWeb {
  pub_titulo: string;
  pub_slug: string | null;
  imagen: string | null;
  categoria: string | null;
}

export type ModalidadPago = 'contado' | 'credito';

export interface CarritoItem {
  car_id: number;
  car_idpro: number;
  car_idvarp: number | null;

  car_tipdes: number | null;
  car_descue: number;
  car_tipcam: number | null;
  car_codcup: string | null;
  car_coddes: string | null;

  car_cantid: number;
  car_precio: number;
  car_subtot: number;
  car_total: number;
  car_creado: string;

  /**
   * car_recargopct y car_montocuo son una FOTO del momento en que se agregó.
   * Los tres últimos vienen en null cuando la modalidad es contado.
   */
  car_modpag: ModalidadPago;
  car_numcuo: number | null;
  car_recargopct: number | null;
  car_montocuo: number | null;

  stock_disponible?: number | null;
  controla_stock?: boolean;

  /**
   * Solo vienen cuando el producto se carga con la fila (whenLoaded).
   * cantidad_minima es el piso real del selector: 1 en general, o el
   * umbral mayorista cuando solo_mayorista es true (punto 8 de la
   * instrucción "Carrito Completo").
   */
  cantidad_minima?: number | null;
  solo_mayorista?: boolean;

  /**
   * IVA contenido en car_total (no se suma aparte: car_total ya lo trae
   * adentro). Es el desglose informativo que exige mostrar la Ley 6380/2019.
   */
  car_iva?: number;

  prd_descri: string;
  publicacion_web: CarritoPublicacionWeb | null;
  variacion: CarritoVariacion | null;
}

export interface CarritoListResponse {
  data: CarritoItem[];
}

export interface CarritoItemResponse {
  message: string;
  data: CarritoItem;
}

export interface CarritoMensajeResponse {
  message: string;
}
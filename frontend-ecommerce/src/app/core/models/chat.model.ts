/** Tarjeta de producto: mismo formato que la vitrina (VitrinaController). */
export interface TarjetaChat {
  pub_id: number;
  prd_id: number;
  titulo: string;
  slug: string;
  imagen: string | null;
  precio_original: number;
  precio_final: number;
  descuento_pct: number | null;
  stock: number;
  controla_stock: boolean;
}

/** Botón que el cliente confirma. El bot nunca modifica el carrito. */
export interface AccionChat {
  tipo: 'agregar_carrito';
  pub_id: number;
  prd_id: number;
  cantidad: number;
  titulo: string;
}

export interface RespuestaChat {
  token: string;
  texto: string;
  productos: TarjetaChat[];
  acciones: AccionChat[];
  derivada: boolean;
}

export interface MensajeChat {
  rol: 'usuario' | 'asistente';
  texto: string;
  productos?: TarjetaChat[];
  acciones?: AccionChat[];
  error?: boolean;
}

export interface HistorialChat {
  token: string;
  mensajes: { rol: 'usuario' | 'asistente'; texto: string; fecha: string | null }[];
}
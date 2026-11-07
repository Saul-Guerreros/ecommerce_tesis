export interface ValorNosotros {
  icono: string;
  titulo: string;
  descripcion: string | null;
}

export interface CifraNosotros {
  valor: string;
  etiqueta: string;
}

export interface PaginaNosotros {
  pno_titulo: string;
  pno_subtitulo: string | null;
  pno_historia: string | null;
  pno_mision: string | null;
  pno_vision: string | null;
  pno_valores: ValorNosotros[];
  pno_cifras: CifraNosotros[];
  pno_imagen_url: string | null;
  pno_publicado: boolean;
  pno_actualizado: string | null;
}

/** Mantener en sincronía con PaginaNosotros::ICONOS (backend). */
export const ICONOS_NOSOTROS: { valor: string; etiqueta: string }[] = [
  { valor: 'star', etiqueta: 'Estrella' },
  { valor: 'heart', etiqueta: 'Corazón' },
  { valor: 'happy', etiqueta: 'Cara feliz' },
  { valor: 'users', etiqueta: 'Personas' },
  { valor: 'check', etiqueta: 'Check' },
  { valor: 'bolt', etiqueta: 'Rayo' },
  { valor: 'lifesaver', etiqueta: 'Soporte' },
  { valor: 'future', etiqueta: 'Futuro' },
  { valor: 'clock', etiqueta: 'Reloj' },
  { valor: 'desktop', etiqueta: 'Computadora' },
  { valor: 'cog', etiqueta: 'Engranaje' },
  { valor: 'code', etiqueta: 'Código' },
  { valor: 'cart', etiqueta: 'Carrito' },
  { valor: 'tag', etiqueta: 'Etiqueta' },
  { valor: 'world', etiqueta: 'Mundo' },
  { valor: 'location', etiqueta: 'Ubicación' },
];
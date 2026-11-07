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
}

export interface DatosContacto {
  dco_titulo: string;
  dco_intro: string | null;
  dco_direccion: string | null;
  dco_referencia: string | null;
  dco_telefono: string | null;
  dco_email: string | null;
  dco_whatsapp: string | null;
  dco_whatsapp_mensaje: string | null;
  dco_horario: string | null;
  dco_mapa_lat: number | null;
  dco_mapa_lng: number | null;
  dco_mapa_zoom: number;
  dco_facebook: string | null;
  dco_instagram: string | null;
  dco_youtube: string | null;
  dco_tiktok: string | null;
}

export interface RedSocial {
  nombre: string;
  url: string;
  icono: string | null; // null = sin ícono en UIkit (se usa SVG propio)
}
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
  dco_publicado: boolean;
  dco_actualizado?: string | null;
}
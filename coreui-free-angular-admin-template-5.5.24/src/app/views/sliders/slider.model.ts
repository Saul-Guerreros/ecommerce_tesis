export interface Slider {
  sld_id?: number;
  sld_prdid: number;
  sld_titulo: string;
  sld_subtitulo?: string;
  sld_label?: string;
  sld_imagen?: string;
  sld_enlace?: string;
  sld_orden?: number;
  sld_tipo?: number;
  sld_estado?: number; // 1 = Activo, 2 = Inactivo
  sld_color?: string;
  sld_creado?: string;
}

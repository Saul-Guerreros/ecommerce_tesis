export interface Slider {
  sld_id?: number;
  sld_prdid: number;
  sld_titulo: string;
  sld_subtitulo?: string;
  sld_label?: string;
  sld_imagen?: string;
  sld_enlace?: string;
  sld_orden?: number;
  sld_tipo?: number; // 1 = Principal, 2 = Secundario, 3 = Terciario (spotlight)
  sld_posicion?: number; // Solo si sld_tipo = 2 (secundario): 1 = Izquierda, 2 = Derecha
  sld_estado?: number; // 1 = Activo, 2 = Inactivo
  sld_color?: string;
  sld_creado?: string;
}
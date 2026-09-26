/**
 * Shape de cada banner tal como lo arma
 * VitrinaController::formatearSlider() en el backend.
 */
export interface SliderVitrina {
  sld_id: number;
  titulo: string;
  subtitulo: string | null;
  label: string | null;
  enlace: string | null;
  color: string | null;
  imagen: string | null; // URL absoluta armada por el backend (asset('storage/...'))
}

export interface SlidersHomeResponse {
  data: {
    principales: SliderVitrina[]; // sld_tipo = 1 (hero)
    secundarios_izquierda: SliderVitrina[]; // sld_tipo = 2, sld_posicion = 1
    secundarios_derecha: SliderVitrina[]; // sld_tipo = 2, sld_posicion = 2
    terciarios: SliderVitrina[]; // sld_tipo = 3 (product spotlight)
  };
}
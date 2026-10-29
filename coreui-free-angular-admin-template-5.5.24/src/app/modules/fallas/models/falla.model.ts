export interface TipoDispositivoOpcion {
  tdi_id: number;
  tdi_codigo: string;
  tdi_descri: string;
}

export interface CategoriaFallaOpcion {
  caf_id: number;
  caf_codigo?: string;
  caf_descri: string;
}
  
export interface Falla {
  fll_id?: number;
  fll_codigo: string;
  fll_descri: string;
  fll_tdiid: number | null;
   fll_cafid?: number | null;
  categoria?: CategoriaFallaOpcion | null;
  fll_estado?: number;
  fll_creado?: string;

  tipo_dispositivo?: TipoDispositivoOpcion | null;
  usos_count?: number;
}

export interface FallaConfig {
  tipos_dispositivo: TipoDispositivoOpcion[];
  categorias: CategoriaFallaOpcion[];
}

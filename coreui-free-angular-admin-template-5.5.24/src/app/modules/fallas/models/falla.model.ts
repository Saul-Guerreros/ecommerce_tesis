export interface TipoDispositivoOpcion {
  tdi_id: number;
  tdi_codigo: string;
  tdi_descri: string;
}

export interface Falla {
  fll_id?: number;
  fll_codigo: string;
  fll_descri: string;
  /** null = falla genérica (aplica a cualquier equipo) */
  fll_tdiid: number | null;
  fll_estado?: number;
  fll_creado?: string;

  tipo_dispositivo?: TipoDispositivoOpcion | null;
  usos_count?: number;
}

export interface FallaConfig {
  tipos_dispositivo: TipoDispositivoOpcion[];
}

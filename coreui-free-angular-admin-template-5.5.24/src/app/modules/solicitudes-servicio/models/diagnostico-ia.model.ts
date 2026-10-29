export type NivelIa = 'ALTA' | 'MEDIA' | 'BAJA';

export interface DiagnosticoIa {
  dpr_id: number;
  disponible: boolean;
  garantia: boolean;
  resumen: string | null;
  confianza: NivelIa | null;
  preguntas: string[];
  fallas: { fll_id: number; descripcion: string; probabilidad: NivelIa }[];
  trabajos: { trb_id: number; descripcion: string; precio: number | null }[];
  estimacion: { costo_min: number; costo_max: number; dias: number | null } | null;
  coincidencia: 'TOTAL' | 'PARCIAL' | 'NINGUNA' | null;
  creado: string;
  tipo_dispositivo?: { tdi_id: number; tdi_descri: string | null; detectado: boolean };
}

export interface DiagnosticoIaPayload {
  sse_tdiid?: number | null; 
  sse_marca: string;
  sse_modelo: string;
  sse_proble: string;
  sse_garant: boolean;
  
}
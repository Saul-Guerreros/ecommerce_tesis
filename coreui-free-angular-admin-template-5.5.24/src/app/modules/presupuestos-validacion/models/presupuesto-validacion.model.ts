import { Presupuesto } from '../../ordenes-tecnico/models/orden-tecnico.model';

export interface PresupuestoFila {
  pse_id: number;
  pse_numero: string;
  pse_versio: number;
  estado: { codigo: string; descri: string };
  pse_total: number;
  pse_creado: string;
  ose_numero: string | null;
  sse_numero: string | null;
  cliente: string | null;
  equipo: string | null;
  tecnico: string | null;
}

export interface PresupuestoRevision {
  presupuesto: Presupuesto;
  ose_numero: string;
  sse_numero: string;
  sse_garant: boolean;
  cliente: string | null;
  equipo: string;
  tecnico: string | null;
  diagnostico: { causa: string; tiempo: number | null; fallas: string[] } | null;
}

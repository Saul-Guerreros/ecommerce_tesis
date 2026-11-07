export interface ResenaAdmin {
  res_id: number;
  producto: string | null;
  slug: string | null;
  cliente: string | null;
  cedula: string | null;
  pedido: string | null;
  res_califi: number;
  res_titulo: string | null;
  res_coment: string;
  res_editad: boolean;
  res_estado: number;
  visible: boolean;
  res_motocu: string | null;
  res_respue: string | null;
  res_fecres: string | null;
  respondido_por: string | null;
  res_creado: string | null;
}

export interface ResenaFiltros {
  search?: string;
  estado?: string;
  calificacion?: string;
  respuesta?: string;
  page?: number;
  per_page?: number;
}

export interface ResumenResenasAdmin {
  total: number;
  ocultas: number;
  sin_respuesta: number;
}

export interface ListadoResenas {
  data: ResenaAdmin[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
  resumen: ResumenResenasAdmin;
}
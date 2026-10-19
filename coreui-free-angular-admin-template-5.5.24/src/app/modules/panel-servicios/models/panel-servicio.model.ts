export interface PanelResumen {
  por_estado: { codigo: string; descri: string; total: number }[];
  tarjetas: {
    pendientes_recepcion: number;
    en_taller: number;
    presupuestos_validar: number;
    esperando_cliente: number;
    por_vencer: number;
    listos_entregar: number;
    cobros_pendientes: number;
  };
  tecnicos: { usu_id: number; nombre: string; abiertas: number }[];
}

export interface PanelFila {
  sse_id: number;
  sse_numero: string;
  ose_numero: string | null;
  sse_creado: string;
  cliente: string | null;
  equipo: string;
  sse_garant: boolean;
  es_reclamo: boolean;
  dias_taller: number | null;
  ejes: {
    servicio: { codigo: string; descri: string };
    ingreso: string | null;
    pago: { codigo: string; descri: string } | null;
    egreso: string | null;
  };
  presupuesto: { numero: string; versio: number; estado: string; fecven: string | null } | null;
  sse_total: number;
  tecnico: { usu_id: number; nombre: string | null } | null;
  acciones: { reasignar: boolean; entregar: boolean; const_recepcion: boolean; const_retiro: boolean };
}

export interface PanelConfig {
  estados_servicio: { ets_id: number; ets_codigo: string; ets_descri: string }[];
  tecnicos: { usu_id: number; nombre: string }[];
}

export interface EntregaConfig {
  sse_numero: string;
  cliente: string | null;
  equipo: string;
  ingreso_a_domicilio: boolean;
  sse_dirid: number | null;
  sse_sucid: number | null;
  titular: { nrodoc: string | null; nombre: string | null; apelli: string | null; telef: string | null };
  sucursales: { suc_id: number; suc_nombre: string }[];
  direcciones: { dir_id: number; resumen: string }[];
  accesorios: { rac_id: number; rac_descri: string }[];
  cobro: { habilitado: boolean; motivo: string | null; total: number };
}

export interface PanelPaginado {
  data: PanelFila[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; desde: number; hasta: number };
}

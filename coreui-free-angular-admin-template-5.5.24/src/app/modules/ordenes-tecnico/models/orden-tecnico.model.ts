export interface OrdenTarjeta {
  sse_id: number;
  sse_numero: string;
  ose_numero: string | null;
  equipo: string;
  sse_garant: boolean;
  /** Garantía de compra: la gestiona la empresa responsable, no hay diagnóstico ni presupuesto. */
  es_garantia_compra: boolean;
  estado: string;
  recibido: string | null;
  tecnico: string | null;
}

export interface TableroResponse {
  data: OrdenTarjeta[];
  meta: { es_admin: boolean; tecnicos: { usu_id: number; nombre: string }[] };
}

export interface FallaDiagnostico {
  fll_id: number;
  fll_descri?: string | null;
  obs: string | null;
}

// ---------- Garantía de compra: gestión con la empresa responsable ----------

export type ResultadoGarantia = 'REPARADO' | 'REEMPLAZADO' | 'RECHAZADO';

export interface EmpresaGarantia {
  pro_id: number;
  razon: string;
  telef: string | null;
  email: string | null;
}

export interface ContactoEmpresa {
  nombre: string;
  cargo: string | null;
  telef: string | null;
  email: string | null;
}

/** Empresa elegible en la gestión (con sus contactos, para llamar o escribir). */
export interface EmpresaOpcion extends EmpresaGarantia {
  contactos: ContactoEmpresa[];
}

export interface GestionGarantia {
  gpr_proid: number | null;
  empresa: EmpresaGarantia | null;
  gpr_nrocaso: string | null;
  gpr_contacto: string | null;
  gpr_fecenv: string | null;
  gpr_fecrec: string | null;
  gpr_result: ResultadoGarantia | null;
  resultado: string | null;
  gpr_nroserr: string | null;
  gpr_obs: string | null;
}

export interface GarantiaEmpresa {
  producto: string | null;
  pedido: string | null;
  /** Empresa vinculada al producto comprado (sugerida al iniciar la gestión). */
  empresa_producto_id: number | null;
  gestion: GestionGarantia | null;
}

export interface DatosGarantiaPayload {
  gpr_proid: number | null;
  gpr_nrocaso: string | null;
  gpr_contacto: string | null;
  gpr_obs: string | null;
}

export interface ResolverGarantiaPayload {
  gpr_result: ResultadoGarantia;
  gpr_fecrec?: string | null;
  gpr_nroserr?: string | null;
  gpr_obs?: string | null;
}

export interface OrdenTrabajo {
  sse_id: number;
  sse_numero: string;
  sse_garant: boolean;
  es_garantia_compra: boolean;
  garantia_empresa: GarantiaEmpresa | null;
  estado: { codigo: string; descri: string };
  cliente: string | null;
  equipo: { tdi_id: number; tipo: string | null; sse_marca: string; sse_modelo: string; sse_nroser: string | null };
  sse_proble: string;
  recepcion: {
    rse_fecha: string;
    rse_estfis: string;
    rse_obs: string | null;
    tiene_clave: boolean;
    tecnico: string | null;
    accesorios: string[];
  } | null;
  adjuntos: { sad_id: number; sad_tipo: string; sad_nombre: string | null; url: string }[];
  orden: {
    ose_id: number;
    ose_numero: string;
    ose_fecini: string;
    ose_fecfin: string | null;
    ose_result: string | null;
    ose_proced: string | null;
  } | null;
  diagnostico: {
    dia_causa: string;
    dia_repara: boolean;
    dia_reqrep: boolean;
    dia_tiempo: number | null;
    dia_fecha: string;
    fallas: FallaDiagnostico[];
  } | null;
  presupuesto: Presupuesto | null;
  versiones: { pse_id: number; pse_numero: string; pse_versio: number; estado: string; codigo: string; pse_total: number }[];
  repuestos: RepuestoUsado[];
  ose_ajuste: boolean;
  ose_gardia: number | null;
  totales: { sse_subtot: number; sse_cosret: number; sse_total: number };
  acciones: {
    iniciar: boolean;
    diagnosticar: boolean;
    cerrar_no_repara: boolean;
    presupuestar: boolean;
    enviar_presupuesto: boolean;
    iniciar_reparacion: boolean;
    ejecutar: boolean;
    solicitar_complementario: boolean;
    descartar_complementario: boolean;
    // Garantía de compra
    iniciar_garantia: boolean;
    garantia_editar: boolean;
    garantia_enviar: boolean;
    garantia_resolver: boolean;
  };
}

export interface FallaOpcion {
  fll_id: number;
  fll_codigo: string;
  fll_descri: string;
  fll_tdiid: number | null;
}

export interface TrabajoOpcion {
  trb_id: number;
  trb_codigo: string;
  trb_descri: string;
  trb_precio: number;
  tasa_iva: number;
}

export interface OrdenConfig {
  fallas: FallaOpcion[];
  precio_revision: number | null;
  max_fotos: number;
  trabajos: TrabajoOpcion[];
  /** Empresas a las que se puede gestionar una garantía de compra. */
  proveedores: EmpresaOpcion[];
}

export type TipoLinea = 'REPUESTO' | 'MANO_OBRA';

export interface PresupuestoLinea {
  psd_id: number;
  psd_tipo: TipoLinea;
  psd_prdid: number | null;
  psd_trbid: number | null;
  psd_descri: string;
  psd_cantid: number;
  psd_precio: number;
  psd_iva: number;
  psd_total: number;
  psd_reserv: boolean;
  precio_lista?: number | null;
  disponible?: number;
}

export interface Presupuesto {
  pse_id: number;
  pse_numero: string;
  pse_versio: number;
  estado: { codigo: string; descri: string };
  pse_subtot: number;
  pse_impues: number;
  pse_total: number;
  pse_fecven: string | null;
  pse_obs: string | null;
  pse_obsval: string | null;
  pse_motrec: string | null;
  pse_enviad: string | null;
  pse_respon: string | null;
  detalles: PresupuestoLinea[];
}

export interface ProductoBusqueda {
  prd_id: number;
  prd_codinv: string;
  descri: string;
  precio: number | null;
  tasa_iva: number;
  controla: boolean;
  disponible: number;
}

export interface LineaPayload {
  tipo: TipoLinea;
  prd_id?: number | null;
  trb_id?: number | null;
  descri?: string;
  cantid: number;
  precio?: number | null;
}

export interface RepuestoUsado {
  osr_id: number;
  descri: string;
  prd_codinv: string | null;
  osr_cantap: number;
  osr_cantid: number;
  osr_precio: number;
  osr_presup: boolean;
}

export interface FinalizarPayload {
  ose_proced: string;
  ose_result: 'REPARADO' | 'PARCIAL';
  ose_gardia: number;
  repuestos: Record<number, number>;
}

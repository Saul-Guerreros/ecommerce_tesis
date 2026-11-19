/**
 * Detalle completo que devuelve SolicitudAdminResource.
 *
 * Los bloques personal / laboral / referencias / detalles / credito
 * vienen del Resource del cliente, que el Resource admin compone. Los
 * bloques analisis / evaluacion / cronograma / historial / acciones son
 * exclusivos del panel.
 */

export interface TramoCuota {
  importe: number;
  desde: number;
  hasta: number;
}

export interface CuotaCronograma {
  numero: number;
  vencimiento: string | null;
  importe: number;
  composicion: string | null;
}

export interface LineaHistorial {
  sch_id: number;
  fecha: string;
  estado_anterior: string | null;
  estado_nuevo: string | null;
  codigo_nuevo: string | null;
  autor: string;
  observacion: string | null;
}

/**
 * Una línea de la compra, tal como la envía SolicitudDetalleResource.
 *
 * Los nombres son los de la columna (scd_*) porque es lo que serializa
 * el backend. Renombrarlos acá dejaría la tabla en blanco sin ningún
 * error visible, que es justo lo que pasaba antes.
 */
export interface DetalleSolicitudLinea {
  scd_id: number;
  scd_prdid: number;
  scd_descri: string;
  scd_cantid: number;
  scd_preuni: number;
  scd_numcuo: number;
  scd_recpct: number;
  scd_moncuo: number;
  scd_subtot: number;
  scd_iva: number;

  /** Calculados en el modelo: cuota y recargo de la línea completa. */
  total_contado: number;
  recargo: number;
  cuota_mensual: number;

  /** Título y slug del catálogo. Null si la publicación web no existe. */
  producto?: { pub_titulo: string; pub_slug: string | null } | null;
}

export interface PlanDisponible {
  cantidad: number;
  recargo_pct: number;
  monto_cuota: number;
  total_linea: number;
  actual: boolean;
}

export interface SolicitudCreditoDetalle {
  scr_id: number;
  numero: string;

  /** Bloques heredados del Resource del cliente. Tipado laxo a propósito:
   *  el detalle los muestra tal cual, sin lógica sobre cada campo. */
  personal: Record<string, any>;
  laboral: Record<string, any>;
  referencias: Record<string, any>[];
  detalles: DetalleSolicitudLinea[];
  credito: Record<string, any>;
  estado: { codigo: string; descripcion: string };
  fecha?: string;

  cliente: { cli_id: number; nombre: string };

  analisis: {
    cuota_mas_alta: TramoCuota | null;
    cuota_mas_baja: TramoCuota | null;
    relacion_cuota_ingreso: number | null;
    ingreso_mensual: number;
    tramos: TramoCuota[];
  };

  evaluacion: {
    observaciones: string | null;
    motivo_codigo: string | null;
    motivo: string | null;
    detalle_rechazo: string | null;
    fecha_decision: string | null;
    usuario_decision: string | null;
    vigencia_hasta: string | null;
    oferta_vencida: boolean;
    fecha_aceptacion: string | null;
  };

  cronograma: {
    fecha_primera_cuota: string | null;
    cuotas: CuotaCronograma[];
    estimado: CuotaCronograma[];
  };

  historial: LineaHistorial[];

  acciones: {
    puede_tomar: boolean;
    puede_decidir: boolean;
    puede_editar: boolean;
  };
}

// ---------- Payloads ----------

export interface DecisionPayload {
  decision: 'APROBAR' | 'RECHAZAR';
  observaciones?: string | null;
  vigencia_dias?: number;
  motivo?: string;
  detalle?: string | null;
}

export interface RespuestaSolicitud {
  message: string;
  data: SolicitudCreditoDetalle;
}
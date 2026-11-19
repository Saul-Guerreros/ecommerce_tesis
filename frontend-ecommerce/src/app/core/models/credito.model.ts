import { CarritoItem } from './carrito.model';

/**
 * Modelos del wizard de Solicitud de Crédito (lado cliente).
 *
 * Nombrado "credito" y no "solicitud-credito" a propósito: el proyecto
 * ya tiene core/models/solicitud-compra.model.ts, usado por
 * producto-detalle.component.ts para una funcionalidad vieja (guardar en
 * sessionStorage un solo producto al tocar "Solicitar Crédito" desde el
 * detalle). Ese flujo quedó obsoleto desde que decidiste que el crédito
 * se arma en el carrito -- ver nota de limpieza al final de esta
 * entrega. Hasta que decidas borrarlo, este archivo nuevo no debe
 * colisionar con el viejo.
 */

import { MedioRecepcion, TipoDocumentoFacturacion, TitularFacturacion } from './checkout.model';
import { DatoFiscal } from './dato-fiscal.model';
import { EntregaResumenCard, ProductoResumenCardItem } from './resumen-compra.model';


export type EstadoCivil = 'SOLTERO' | 'CASADO' | 'DIVORCIADO' | 'VIUDO' | 'UNION_LIBRE';
export type TipoVivienda = 'PROPIA' | 'ALQUILER';
export type RelacionReferencia = 'FAMILIAR' | 'LABORAL' | 'AMISTAD';

export interface TipoProfesionCatalogo {
  tpr_id: number;
  tpr_descri: string;
}

export interface ProfesionCatalogo {
  pro_id: number;
  pro_descri: string;
}

export interface PerfilPrecarga {
  scr_cedula: string | null;
  scr_nombre: string | null;
  scr_apelli: string | null;
  scr_fechna: string | null;
  scr_celula: string | null;
  scr_email: string | null;
}

export interface ConfigCreditoResponse {
  tipos_profesion: TipoProfesionCatalogo[];
  profesiones: ProfesionCatalogo[];
  estados_civiles: EstadoCivil[];
  tipos_vivienda: TipoVivienda[];
  relaciones: RelacionReferencia[];
  perfil: PerfilPrecarga;

  /** 1 a 4. El wizard salta directo acá si hay un borrador a medias. */
  paso_actual: number;

  /** Totales del credito, para el panel lateral de los 4 pasos. */
  resumen: ResumenCredito;

  personal: SolicitudPersonal | null;
  laboral: SolicitudLaboral | null;
  referencias: SolicitudReferencia[] | null;
}

// ---------- Paso 1: Datos personales (3 sub-pasos) ----------

export interface SolicitudPersonal {
  // 1/3 Identificación
  scr_cedula: string;
  scr_nombre: string;
  scr_apelli: string;
  scr_fechna: string;
  scr_celula: string;
  scr_email: string;

  // 2/3 Domicilio
  scr_ciuid: number;
  dep_id: number | null;
  ciudad: string | null;
  departamento: string | null;
  scr_barrio: string;
  scr_calle: string;
  scr_nrocasa: string | null;
  scr_referen: string | null;

  // 3/3 Situación personal
  scr_estciv: EstadoCivil;
  /** Relacion laboral: FK propia, independiente de la profesion. */
  scr_tprid: number;
  tipo_profesion: string | null;
  scr_proid: number;
  profesion: string | null;
  scr_tipviv: TipoVivienda;
  scr_sertit: string | null;
  scr_relsti: string | null;

  resumen: string | null;
}

/** Lo que el formulario envía: igual a SolicitudPersonal menos los campos derivados. */
export type GuardarPersonalRequest = Omit<
  SolicitudPersonal,
  'dep_id' | 'ciudad' | 'departamento' | 'profesion' | 'tipo_profesion' | 'resumen'
>;

// ---------- Paso 2: Datos laborales (2 sub-pasos) ----------

export interface SolicitudLaboral {
  scr_empres: string;
  scr_dirlab: string;
  scr_tellab: string;
  scr_cargo: string;
  scr_ingres: number;
  scr_fecing: string;
  antiguedad: { anios: number; meses: number; texto: string } | null;
  resumen: string | null;
}

export type GuardarLaboralRequest = Omit<SolicitudLaboral, 'antiguedad' | 'resumen'>;

// ---------- Paso 3: Referencias personales (2 sub-pasos) ----------

export interface SolicitudReferencia {
  scf_orden: number;
  scf_nombre: string;
  scf_celula: string;
  scf_relaci: RelacionReferencia;
  relacion?: string;
}

export type GuardarReferenciaRequest = Omit<SolicitudReferencia, 'scf_orden' | 'relacion'>;

// ---------- Paso 4: Confirmación ----------

export interface ResumenCredito {
  total_contado: number;
  recargo: number;
  total: number;
  impuestos: number;
  plazo: number;
  cuota_mensual: number;
  planes_mixtos: boolean;
}

export interface ConfigConfirmacionResponse {
  personal: SolicitudPersonal;
  laboral: SolicitudLaboral;
  referencias: SolicitudReferencia[];
  resumen: ResumenCredito;
  items: CarritoItem[];
}

// ---------- Solicitud ya generada ----------

export interface SolicitudDetalleItem {
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
  total_contado: number;
  recargo: number;
  cuota_mensual: number;
    producto: { pub_titulo: string; pub_slug: string | null; imagen?: string | null } | null;
}

export interface SolicitudCreditoGenerada {
  scr_id: number;
  numero: string;
  fecha: string;
  scr_declar: boolean;
  estado: { codigo: string; descripcion: string };
  personal: SolicitudPersonal & { nombre_completo: string };
  laboral: SolicitudLaboral;
  referencias: SolicitudReferencia[];
  detalles: SolicitudDetalleItem[];
  credito: {
    total_contado: number;
    recargo: number;
    total: number;
    impuestos: number;
    plazo: number;
    cuota_mensual: number;
  };

  /** Bloque de la etapa D: vigencia y acciones permitidas al cliente. */
  oferta?: OfertaCredito;

  /** Pedido generado a partir de la solicitud (solo en estado CONVERTIDA, en el detalle). */
  pedido?: { numero: string } | null;

  /** Cronograma persistido. Solo viene en el detalle, no en el listado. */
  cronograma?: CuotaCronograma[];
}

export interface SolicitudGeneradaResponse {
  message: string;
  data: SolicitudCreditoGenerada;
}

export interface DisponibilidadCreditoResponse {
  data: {
    disponible: boolean;
    solicitud: { scr_id: number; numero: string; estado: string | null } | null;
  };
}

// ---------- Oferta aprobada: aceptación del cliente (etapa D) ----------

export interface CuotaCronograma {
  numero: number;
  /** Y-m-d. NULL si el administrador todavía no fijó la fecha de la primera cuota. */
  vencimiento: string | null;
  importe: number;
  composicion: string | null;
}

export interface OfertaCredito {
  vigencia_hasta: string | null;
  /** dd/mm/YYYY, listo para mostrar. */
  vigencia_texto: string | null;
  dias_restantes: number | null;
  /** El backend decide si los botones [ACEPTAR] / [Rechazar oferta] se ofrecen. */
  puede_responder: boolean;
  vencida: boolean;
  fecha_decision: string | null;
  fecha_aceptacion: string | null;
  motivo_rechazo: string | null;
  /** Cantidad de días que el admin otorgó al aprobar (cabecera de la solicitud). */
  vigencia_dias: number | null;
  /** Aceptó las condiciones y todavía puede armar el pedido. */
  puede_continuar: boolean;
}

export interface AceptarOfertaRequest {
  acepta_condiciones: boolean;
  acepta_firma: boolean;
}

export interface RespuestaOfertaResponse {
  message: string;
  data: SolicitudCreditoGenerada;
}

/**
 * Clase de color del badge de estado. Una sola definición para el listado
 * y el detalle: si se agrega un estado, se cambia acá.
 */
export function claseEstadoSolicitud(codigo: string): string {
  const clases: Record<string, string> = {
    PENDIENTE: 'uk-label',
    EN_REVISION: 'uk-label uk-label-warning',
    APROBADA: 'uk-label uk-label-success',
    ACEPTADA: 'uk-label uk-label-success',
    RECHAZADA: 'uk-label uk-label-danger',
    ANULADA: 'uk-label',
    CONVERTIDA: 'uk-label uk-label-success',
  };
  return clases[codigo] ?? 'uk-label';
}

// ---------- Checkout de la solicitud aceptada (etapa D) ----------

export interface CheckoutCreditoContexto {
  solicitud: {
    scr_id: number;
    numero: string;
    vigencia_texto: string | null;
    dias_restantes: number | null;
  };
  titular: TitularFacturacion;
  datos_fiscales: DatoFiscal[];
  entrega: EntregaResumenCard;
  items: ProductoResumenCardItem[];
  resumen: {
    total_contado: number;
    recargo: number;
    impuestos: number;
    total: number;
    plazo: number;
    cuota_mensual: number;
  };
  cronograma: CuotaCronograma[];
}

export interface ConfirmarCheckoutCreditoRequest {
  tipo_documento: TipoDocumentoFacturacion;
  dfi_id?: number;
  pfa_recibe: MedioRecepcion;
  acepta_condiciones: boolean;
}

export interface PedidoCreditoCreado {
  ped_id: number;
  ped_numero: string;
  ped_total: number;
}
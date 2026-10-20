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
  producto: { pub_titulo: string; pub_slug: string | null } | null;
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
}

export interface SolicitudGeneradaResponse {
  message: string;
  data: SolicitudCreditoGenerada;
}
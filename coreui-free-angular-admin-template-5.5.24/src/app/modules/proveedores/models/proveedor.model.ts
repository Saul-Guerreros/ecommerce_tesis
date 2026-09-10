export interface Contacto {
  cpr_id?: number;
  cpr_proid: number;
  cpr_nombre: string;
  cpr_apellido: string;
  cpr_cargo?: string | null;
  cpr_telef?: string | null;
  cpr_email?: string | null;
  cpr_princi?: number;
  cpr_estado?: number;
}

export interface Proveedor {
  pro_id?: number;
  pro_tprid: number | null;
  pro_ciuid: number | null;
  pro_ruc: string;
  pro_razon: string;
  pro_nomfan?: string | null;
  pro_direccion?: string | null;
  pro_telef?: string | null;
  pro_email?: string | null;
  pro_estado?: number;
  pro_creado?: string;

  tpr_descri?: string;
  ciu_descri?: string;
  contactos?: Contacto[];
  contactos_count?: number;
}

export interface TipoProveedorOption {
  tpr_id: number;
  tpr_descri: string;
}

export interface CiudadOption {
  ciu_id: number;
  ciu_descri: string;
  dep_descri: string;
}

export interface ProveedorConfig {
  tipos: TipoProveedorOption[];
  ciudades: CiudadOption[];
}
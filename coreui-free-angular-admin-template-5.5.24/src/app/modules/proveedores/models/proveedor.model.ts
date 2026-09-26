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

export interface TimbradoProveedor {
  tpv_id?: number;
  tpv_proid: number;
  tpv_tcoid: number | null;
  tpv_numero: string;

  tpv_fechfn: string;      // YYYY-MM-DD
  tpv_estado?: number;
  tco_descri?: string;
  compras_count?: number;  // si > 0 no se puede editar ni eliminar
}

export interface TipoComprobanteOption {
  tco_id: number;
  tco_descri: string;
}
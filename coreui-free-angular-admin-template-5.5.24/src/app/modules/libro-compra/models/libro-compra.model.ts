export interface LibroCompra {
  lcm_id: number;
  lcm_tipo: 'FC' | 'NC';
  lcm_comid: number | null;
  lcm_ncrid: number | null;
  lcm_estado: number;
  lcm_fecha: string;
  lcm_nrodoc: string;
  lcm_proraz: string;
  lcm_proruc: string;
  lcm_exenta: number;
  lcm_grav5: number;
  lcm_grav10: number;
  lcm_iva5: number;
  lcm_iva10: number;
  lcm_total: number;
}

export interface LibroCompraTotales {
  exenta: number;
  grav5: number;
  grav10: number;
  iva5: number;
  iva10: number;
  total: number;
}

export interface ProveedorOption {
  pro_id: number;
  pro_razon: string;
}
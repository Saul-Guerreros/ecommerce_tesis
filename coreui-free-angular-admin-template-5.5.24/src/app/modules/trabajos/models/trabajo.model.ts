export interface TipoIvaOpcion {
  tiv_id: number;
  tiv_descri: string;
  tiv_tasa: number;
}

export interface Trabajo {
  trb_id?: number;
  trb_codigo: string;
  trb_descri: string;
  trb_precio: number;
  trb_tivid: number | null;
  trb_estado?: number;
  trb_creado?: string;

  tipo_iva?: TipoIvaOpcion;
  usos_count?: number;
}

export interface TrabajoConfig {
  tipos_iva: TipoIvaOpcion[];
}

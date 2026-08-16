export interface Propiedad {
  prp_id?: number;
  prp_atbid: number;
  prp_nombre?: string;
  prp_codigo: string;
}

export interface Atributo {
  atb_id?: number;
  atb_nombre: string;
  atb_tipo: number; // 1 Texto, 2 Número, 3 Seleccionable, 4 Seleccionable Múltiple
  atb_estado: number; // 1 Activo, 2 Inactivo
  atb_creado?: string;
  propiedades?: Propiedad[];
}

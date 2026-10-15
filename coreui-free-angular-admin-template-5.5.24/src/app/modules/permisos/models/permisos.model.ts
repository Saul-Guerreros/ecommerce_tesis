export interface RolColumna {
  rol_id: number;
  rol_descri: string;
  /** true = no editable (Administrador: acceso total). */
  bloqueado: boolean;
}

export interface OpcionPermiso {
  permiso: string;
  nombre: string;
  urls: string[];
  /** true = siempre concedido (ej.: dashboard.ver, necesario para entrar al panel). */
  fijo: boolean;
}

export interface GrupoPermisos {
  nombre: string;
  opciones: OpcionPermiso[];
}

export interface MatrizPermisos {
  roles: RolColumna[];
  grupos: GrupoPermisos[];
  /** rol_id → códigos de permiso asignados. */
  asignados: Record<string, string[]>;
}

export interface CambioRol {
  rol_id: number;
  permisos: string[];
}

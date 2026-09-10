export interface Rol {
  rol_id?: number;
  rol_descri: string;
  rol_estado?: number;
  rol_creado?: string;
  permisos?: number[];        // IDs de permiso tildados (viene al editar)
  usuarios_count?: number;
}

export interface PermisoOption {
  prm_id: number;
  prm_descri: string;
}

export interface RolConfig {
  permisos: PermisoOption[];
}
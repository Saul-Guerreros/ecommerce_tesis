export interface PersonaUsuario {
  persona_id?: number;
  nombre_completo?: string;
  nombre1: string;
  nombre2?: string | null;
  apellido1: string;
  apellido2?: string | null;
  cedula: string;
  fecha_nacimiento?: string | null;
}

export type EstadoUsuario = 'ACTIVO' | 'INACTIVO' | 'BLOQUEADO';

export interface SucursalAsignada {
  suc_id: number;
  suc_nombre: string;
  principal: boolean;
}

export interface Usuario {
  usuario_id: number;
  email: string;
  estado: EstadoUsuario;
  rol_id: number;
  rol: string;
  creado?: string;
  persona: PersonaUsuario;
  cargo_id: number;
  cargo: string;
  fecha_ingreso?: string | null;
  sucursales: SucursalAsignada[];
}

export interface RolCatalogo {
  rol_id: number;
  rol_descri: string;
}

export interface CargoCatalogo {
  crg_id: number;
  crg_descr: string;
}

export interface SucursalCatalogo {
  suc_id: number;
  suc_nombre: string;
}

export interface CatalogosUsuario {
  roles: RolCatalogo[];
  cargos: CargoCatalogo[];
  sucursales: SucursalCatalogo[];
}

/**
 * Payload que espera StoreUsuarioRequest / UpdateUsuarioRequest en el backend.
 * password es opcional al editar (si viene vacío, no se cambia).
 */
export interface UsuarioFormValue {
  nombre1: string;
  nombre2?: string | null;
  apellido1: string;
  apellido2?: string | null;
  cedula: string;
  fecha_nacimiento?: string | null;
  cargo_id: number | null;
  rol_id: number | null;
  email: string;
  password?: string;
  sucursales: number[];
  sucursal_principal: number | null;
}

export interface UsuariosPaginados {
  data: Usuario[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
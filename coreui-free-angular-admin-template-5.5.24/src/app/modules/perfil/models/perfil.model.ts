export interface Perfil {
  usuario_id: number;
  email: string;
  estado: 'ACTIVO' | 'INACTIVO' | 'BLOQUEADO';
  rol: string | null;
  permisos: string[];
  persona: {
    per_id: number;
    nombre_completo: string;
    per_nombr1: string;
    per_nombr2: string | null;
    per_apell1: string;
    per_apell2: string | null;
    per_cedula: string | null;
    tipo_documento: string | null;
    per_fechna: string | null;
    avatar_url: string | null;
  };
  celular: string | null;
}

export interface CambioPassword {
  password_actual: string;
  password_nuevo: string;
  password_nuevo_confirmation: string;
}
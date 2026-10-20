export interface Sucursal {
  suc_id?: number;
  suc_empid: number | null;
  suc_ciuid: number | null;
  suc_nombre: string;
  suc_princi?: number;          // 0 = normal, 1 = sucursal principal de la empresa
  suc_establ?: string | null;   // codigo de establecimiento (3 digitos), para Timbrados
  suc_direccion?: string | null;
  suc_telef?: string | null;
  suc_cel?: string | null;
  suc_email?: string | null;
  suc_estado?: number;
  suc_creado?: string;
  suc_retiro?: boolean;         // habilitada para retiro de pedidos web
  suc_horario?: string | null;  // horario de atencion mostrado al cliente
  suc_diaslab?: string;         // mascara de 7 '1'/'0', lunes->domingo: dias habiles para el plazo de retiro
  suc_webstk?: boolean;         // visible en "Ver Stock en Tiendas" (detalle de producto)
  suc_delivery?: boolean;       // despacha envios a domicilio

  // campos calculados que llegan del backend
  emp_razonsocial?: string;
  ciu_descri?: string;
  depositos_count?: number;
}

export interface EmpresaOption {
  emp_id: number;
  emp_razonsocial: string;
}

export interface CiudadOption {
  ciu_id: number;
  ciu_descri: string;
  dep_descri: string;
}

export interface SucursalConfig {
  empresas: EmpresaOption[];
  ciudades: CiudadOption[];
}
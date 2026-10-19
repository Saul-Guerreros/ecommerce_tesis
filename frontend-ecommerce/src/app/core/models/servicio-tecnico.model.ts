import { SucursalRetiro } from './checkout.model';
import { Direccion } from './direccion.model';

export type ModalidadIngreso = 'sucursal' | 'domicilio';

export interface TipoDispositivoOpcion {
  tdi_id: number;
  tdi_codigo: string;
  tdi_descri: string;
}

export interface PedidoGarantia {
  ped_id: number;
  ped_numero: string;
  fecha: string;
  productos: string[];
}

export interface ConfigServicio {
  tipos_dispositivo: TipoDispositivoOpcion[];
  sucursales: SucursalRetiro[];
  direcciones: Direccion[];
  pedidos_garantia: PedidoGarantia[];
  garantia_dias: number;
  max_fotos: number;
}

/** Vista del cliente: solo el estado público (ets_visible). */
export interface ServicioCliente {
  sse_numero: string;
  sse_creado: string;
  equipo: string;
  sse_nroser: string | null;
  sse_garant: boolean;
  estado: string;
  /** 1..5 dentro del timeline; null si está SOLICITADO o CANCELADO. */
  paso_actual: number | null;
  cancelado: boolean;
  accion_pendiente: string | null;

  // solo en el detalle
  sse_proble?: string;
  sse_acceso?: string | null;
  pedido?: string | null;
  ingreso?: {
    a_domicilio: boolean;
    sucursal: string | null;
    direccion: string | null;
    fecha: string | null;
    costo: number;
    estado: string | null;
  };
  fotos?: string[];
  historial?: { fecha: string; coment: string | null }[];
  presupuesto?: PresupuestoCliente | null;

  // Paso 6
  equipo_datos?: { tdi_id: number; marca: string; modelo: string; nroser: string | null };
  reclamo_de?: string | null;
  resultado?: { resultado: string; detalle: string | null; total: number } | null;
  entrega?: { fecha: string; receptor: string } | null;
  garantia?: { dias: number; vence: string; vigente: boolean; puede_reclamar: boolean; reclamo_abierto: boolean } | null;
  constancias?: { recepcion: boolean; retiro: boolean };
}

export interface PresupuestoCliente {
  pse_numero: string;
  pse_versio: number;
  estado: string;
  codigo: 'ENVIADO' | 'APROBADO' | 'RECHAZADO' | 'VENCIDO';
  pse_fecven: string | null;
  pse_obs: string | null;
  pse_motrec: string | null;
  pse_impues: number;
  pse_total: number;
  costo_retiro: number;
  puede_responder: boolean;
  lineas: { tipo: 'REPUESTO' | 'MANO_OBRA'; descri: string; cantid: number; precio: number; total: number }[];
}

export interface ConfiguracionTienda {
  ctn_id: number;
  ctn_pocstk_activo: boolean;
  ctn_pocstk_umbral: number;
  ctn_pago_banco: string | null;
  ctn_pago_banco_titular: string | null;
  ctn_pago_banco_cuenta: string | null;
  ctn_pago_tigo_titular: string | null;
  ctn_pago_tigo_numero: string | null;
  ctn_pago_tigo_referencia: string | null;
  ctn_pago_efectivo_mensaje: string | null;
}

export type ActualizarConfiguracionTiendaPayload = Omit<ConfiguracionTienda, 'ctn_id'>;
export interface ConfiguracionPrecio {
  cpr_id: number;
  cpr_marmin: number;
  cpr_marmaj: number;
  cpr_canminmaj: number;
}

export type ActualizarConfiguracionPrecioPayload = Omit<ConfiguracionPrecio, 'cpr_id'>;
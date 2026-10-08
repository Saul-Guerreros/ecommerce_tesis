export interface ConfiguracionEnvio {
  cfe_id: number;
  cfe_modo: 'gratis' | 'fijo' | 'sucursal';
  cfe_costo_fijo: number | null;
  cfe_monto_minimo: number | null;
}

export type ActualizarConfiguracionEnvioPayload = Omit<ConfiguracionEnvio, 'cfe_id'>;
export interface ConfiguracionTienda {
  ctn_id: number;
  ctn_pocstk_activo: boolean;
  ctn_pocstk_umbral: number;
}

export type ActualizarConfiguracionTiendaPayload = Omit<ConfiguracionTienda, 'ctn_id'>;

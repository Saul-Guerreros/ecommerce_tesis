export interface TipoComprobante {
  tco_id?: number;
  tco_codigo: string;
  tco_descri: string;

  // 1 = necesita timbrado vigente para emitirse
  tco_reqtim?: number;

  // Efecto sobre el stock: 1 suma, -1 resta, 0 neutro
  tco_afecstk?: number;

  tco_estado?: number;
  tco_creado?: string;

  // calculado por el backend
  usos_count?: number;
}

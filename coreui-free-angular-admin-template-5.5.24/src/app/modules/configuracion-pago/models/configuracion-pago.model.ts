/** Momento en que el cheque descuenta stock real. Espejo de ConfiguracionTienda::STOCK_* en la API. */
export type ChequeStockPolitica = 'RECEPCION' | 'ACREDITACION';

export interface ConfiguracionPago {
  ctn_pago_banco: string | null;
  ctn_pago_banco_titular: string | null;
  ctn_pago_banco_cuenta: string | null;
  ctn_pago_tigo_titular: string | null;
  ctn_pago_tigo_numero: string | null;
  ctn_pago_tigo_referencia: string | null;
  ctn_pago_efectivo_mensaje: string | null;
  ctn_pago_cheque_titular: string | null;
  ctn_pago_cheque_mensaje: string | null;
  ctn_pago_cheque_aviso: string | null;
  ctn_pago_cheque_vista: boolean;
  ctn_pago_cheque_diferido: boolean;
  ctn_pago_cheque_retiro: boolean;
  ctn_pago_cheque_envio: boolean;
  ctn_cheque_stock: ChequeStockPolitica;
}
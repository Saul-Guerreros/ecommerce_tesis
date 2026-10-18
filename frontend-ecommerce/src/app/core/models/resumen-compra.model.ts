/**
 * Tipos de las tarjetas de resumen que acompañan al checkout y a "Mis
 * Pedidos": Resumen de Compra, Productos en Carrito, Datos de Entrega y
 * Datos de Facturación.
 *
 * El backend ya normaliza 'entrega', 'facturacion' y 'productos' a esta
 * misma forma tanto en el Paso 3 (PagoPedidoController) como en Mis
 * Pedidos (PedidoClienteDetalleResource) — así los tres componentes
 * compartidos de este archivo sirven sin adaptar nada entre pantallas.
 * La única excepción son los Pasos 1 y 2, que todavía trabajan sobre el
 * borrador del checkout (otra forma de datos): esas páginas adaptan con
 * una función corta antes de pasarle los datos al componente.
 */

export interface ProductoResumenCardItem {
  imagen: string | null;
  nombre: string;
  cantidad: number;
  total: number;
}

export interface EntregaResumenCard {
  modalidad: 'retiro' | 'envio';
  receptor_nombre: string;
  receptor_telefono?: string | null;
  receptor_documento?: string | null;

  suc_nombre?: string | null;
  suc_direccion?: string | null;
  suc_horario?: string | null;

  calle?: string | null;
  nrocasa?: string | null;
  barrio?: string | null;
  ciudad?: string | null;
  departamento?: string | null;
  referencia?: string | null;
}

export interface FacturacionResumenCard {
  tipo_documento: string; // 'CI' | 'RUC'
  nro_documento: string;
  razon_social: string;
  recibe_por: string; // 'CORREO' | 'WHATSAPP'
}
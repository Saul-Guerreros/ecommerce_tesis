/** Análisis de comportamiento de clientes (ComportamientoReporteService). */

export type VistaComportamiento = 'busquedas' | 'patrones' | 'segmentos' | 'cliente' | 'ia';
export type OrigenBusqueda = 'TIENDA' | 'CHATBOT';
export type ClaveSegmento = 'CAMPEON' | 'LEAL' | 'NUEVO' | 'ATENCION' | 'EN_RIESGO' | 'PERDIDO';

export interface FiltrosComportamientoExtra {
  origen?: OrigenBusqueda | null;
  horas?: number;
  meses?: number;
  limite?: number;
}

// ---------- A. Búsquedas ----------

export interface TerminoBuscado {
  termino: string; veces: number; personas: number; sin_resultado: number;
  con_ia: number; tasa_clic: number | null; ultima: string;
}
export interface TotalesBusquedas {
  busquedas: number; terminos: number; personas: number; pct_sin_resultado: number | null;
  pct_con_ia: number | null; tasa_clic: number | null; desde_chatbot: number;
}
export interface TerminoSinResultado { termino: string; veces: number; personas: number; primera: string; ultima: string; }
export interface ProductoBuscado {
  id: number; producto: string; desde_busqueda: number; vistas: number;
  personas: number; unidades: number; conversion: number | null;
}

// ---------- B. Patrones ----------

export interface CeldaFranja { dia: number; dia_texto: string; hora: number; compras: number; total: number; }
export interface TotalesFranja { compras: number; dia_pico: string | null; hora_pico: number | null; max_celda: number; }
export interface ParComprado { producto_a: string; producto_b: string; veces: number; confianza_ab: number | null; confianza_ba: number | null; }
export interface TotalesCanasta { facturas: number; pct_multiproducto: number | null; productos_promedio: number; }
export interface TramoRecompra { etiqueta: string; recompras: number; participacion: number | null; }
export interface TotalesRecompra {
  recompras: number; promedio_dias: number | null; mediana_dias: number | null;
  compradores: number; recurrentes: number; pct_recurrentes: number | null;
}
export interface Modalidad { grupo: 'condicion' | 'entrega'; etiqueta: string; compras: number; total: number; participacion: number | null; }
export interface EtapaEmbudo { etapa: string; cantidad: number; porcentaje: number | null; paso: number | null; }
export interface TotalesEmbudo { pedidos: number; pedidos_cancelados: number; conversion_global: number | null; }
export interface CarritoAbandonado {
  cliente_id: number | null; cliente: string; cuenta: string; items: number; total: number; ultima: string; dias: number;
}
export interface TotalesCarritos { carritos: number; items: number; valor: number; horas: number; }

// ---------- C. Preferencias ----------

export interface Rfm {
  primera: string; ultima: string; dias: number; compras: number; total: number; ticket: number;
  r: number; f: number; m: number; alto_valor: boolean; segmento: ClaveSegmento;
}
export interface ClienteSegmentado extends Rfm { id: number; cliente: string; documento: string | null; }
export interface ResumenSegmento {
  clave: ClaveSegmento; etiqueta: string; color: string; accion: string;
  clientes: number; total: number; participacion: number | null;
}
export interface TotalesSegmentos { clientes: number; segmentos: ResumenSegmento[]; }

export interface ClienteEncontrado { id: number; nombre: string; documento: string | null; }

export interface Preferencia { etiqueta: string; unidades: number; total: number; participacion: number | null; }

export interface PerfilCliente {
  cliente: { id: number; nombre: string; documento: string | null; tipo: string; registrado: string; cuenta: string | null; tiene_cuenta_web: boolean };
  rfm: Rfm | null;
  segmento: (ResumenSegmento & { clave: ClaveSegmento }) | null;
  recompra: { promedio_dias: number | null; proxima_estimada: string | null };
  categorias: Preferencia[];
  marcas: Preferencia[];
  productos: { producto: string; veces: number; unidades: number; total: number; ultima: string }[];
  precios: { minimo: number; promedio: number; maximo: number } | null;
  habitos: { condicion: string | null; entrega: string | null; metodo_pago: string | null; dia: string | null; franja: string | null };
  busquedas: { termino: string; veces: number; resultados: number; ultima: string }[];
  vistos_sin_comprar: { producto: string; vistas: number; ultima: string }[];
  favoritos: { producto: string; desde: string; comprado: boolean }[];
  carrito: { items: number; total: number; ultima: string } | null;
  sugeridos: { producto: string; veces: number }[];
}

// ---------- IA ----------

export interface HallazgoIa {
  titulo: string; evidencia: string; accion: string;
  prioridad: 'alta' | 'media' | 'baja'; area: string;
}
export interface InsightsIa { resumen: string; hallazgos: HallazgoIa[]; generado: string; desde_cache: boolean; }
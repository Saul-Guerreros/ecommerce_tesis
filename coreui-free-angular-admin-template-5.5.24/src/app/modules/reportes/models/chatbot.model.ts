/** Respuestas de /api/admin/reportes/chatbot/* */

export type VistaChatbot = 'periodo' | 'temas' | 'preguntas' | 'sin-respuesta';

export type TipoNoResuelta = 'todos' | 'sin_respuesta' | 'derivada';

export interface FiltrosChatbotExtra {
  limite?: number;
  tipo?: TipoNoResuelta;
}

// ---------- por período ----------
export interface ChatPeriodo {
  periodo: string;
  etiqueta: string;
  conversaciones: number;
  identificadas: number;
  mensajes: number;
  derivadas: number;
  tasa_derivacion: number | null;
  con_sin_respuesta: number;
  tasa_sin_respuesta: number | null;
  turnos_sin_respuesta: number;
  tokens: number;
  latencia_ms: number | null;
}
export interface TotalesChatPeriodo {
  conversaciones: number;
  identificadas: number;
  mensajes: number;
  mensajes_por_conversacion: number | null;
  derivadas: number;
  tasa_derivacion: number | null;
  con_sin_respuesta: number;
  tasa_sin_respuesta: number | null;
  turnos_sin_respuesta: number;
  tokens: number;
  latencia_ms: number | null;
}

// ---------- temas ----------
export interface ChatTema {
  herramienta: string;
  tema: string;
  invocaciones: number;
  conversaciones: number;
  ultima: string;
  participacion: number | null;
}
export interface TotalesChatTemas {
  invocaciones: number;
  mensajes_usuario: number;
  temas: number;
}

// ---------- preguntas ----------
export interface ChatPregunta {
  posicion: number;
  pregunta: string;
  veces: number;
  conversaciones: number;
  ultima: string;
  participacion: number | null;
}
export interface TotalesChatPreguntas {
  preguntas: number;
  veces: number;
  mensajes_usuario: number;
  cobertura: number | null;
}

// ---------- sin respuesta / derivadas ----------
export interface ChatNoResuelta {
  tipo: 'sin_respuesta' | 'derivada';
  tipo_texto: string;
  fecha: string;
  conversacion: number;
  pregunta: string | null;
  detalle: string | null;
}
export interface TotalesChatNoResueltas {
  total: number;
  sin_respuesta: number;
  derivadas: number;
  limite: number;
}

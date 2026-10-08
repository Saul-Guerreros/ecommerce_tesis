export interface FaqTienda {
  fqt_id?: number;
  fqt_tema: string;
  fqt_pregunta: string;
  fqt_respuesta: string;
  fqt_orden: number;
  fqt_activo: boolean;
}

export interface TemaFaq {
  valor: string;
  etiqueta: string;
}

export interface ListadoFaq {
  faqs: FaqTienda[];
  caracteresActivos: number;
}
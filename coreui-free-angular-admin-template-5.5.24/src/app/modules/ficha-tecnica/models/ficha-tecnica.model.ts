/** atb_tipo: 1 = Texto | 2 = Número | 3 = Seleccionable | 4 = Selección múltiple */
export interface CampoFicha {
  atb_id: number;
  atb_nombre: string;
  atb_tipo: 1 | 2 | 3 | 4;
  atb_unidad: string | null;
  obligatorio: boolean;
  orden: number;
  en_plantilla: boolean;
  propiedades: { prp_id: number; prp_nombre: string }[];

  /** 'propio' = guardado en este producto | 'heredado' = viene del producto base */
  origen: 'propio' | 'heredado';
  /** En una variante solo es editable su atributo propio (el que la diferencia). */
  editable: boolean;

  // valor actual (editable en el formulario si editable = true)
  valor_texto: string | null;
  valor_numero: number | null;
  propiedad_ids: number[];
}

/** Lo que viaja al backend dentro del guardado de la Publicación Web. */
export interface ValorFicha {
  atb_id: number;
  valor_texto: string | null;
  valor_numero: number | null;
  propiedad_ids: number[];
}

import { CommonModule } from '@angular/common';
import { Component, Input, OnInit, inject } from '@angular/core';
import { FormGroup, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { SliderService } from '../services/slider.service';

export type TipoDestino = 'producto' | 'categoria' | 'marca' | 'campania' | 'busqueda' | 'url' | null;

interface OpcionDestino {
  id: number;
  nombre: string;
  slug?: string;
}

const CON_ID = ['producto', 'categoria', 'marca', 'campania'];

/** Valida el bloque de destino del formulario. Devuelve el mensaje de error o null. */
export function validarDestino(v: any): string | null {
  const tipo = v.sld_destino_tipo;
  const valor = String(v.sld_destino_valor ?? '').trim();

  if (CON_ID.includes(tipo) && !v.sld_destino_id) return 'Selecciona a dónde lleva el banner';
  if (tipo === 'busqueda' && !valor) return 'Escribe el texto de búsqueda';
  if (tipo === 'url' && !/^(\/\S*|https?:\/\/\S+)$/.test(valor)) {
    return 'El enlace debe empezar con "/" (ruta de la tienda) o con https://';
  }
  return null;
}

@Component({
  selector: 'app-destino-slider',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule],
  templateUrl: './destino-slider.component.html',
})
export class DestinoSliderComponent implements OnInit {
  /** El FormGroup del padre: debe tener sld_destino_tipo, sld_destino_id y sld_destino_valor. */
  @Input({ required: true }) form!: FormGroup;

  private sliderService = inject(SliderService);

  readonly tipos: { valor: TipoDestino; etiqueta: string; ayuda: string }[] = [
    { valor: null, etiqueta: 'Sin enlace', ayuda: 'El banner es solo informativo y no muestra botón.' },
    { valor: 'producto', etiqueta: 'Producto', ayuda: 'Lleva a la página de un producto publicado.' },
    { valor: 'campania', etiqueta: 'Campaña', ayuda: 'Muestra todos los productos de una campaña de descuento. El banner se oculta solo cuando la campaña termina.' },
    { valor: 'categoria', etiqueta: 'Categoría', ayuda: 'Muestra todos los productos de una categoría.' },
    { valor: 'marca', etiqueta: 'Marca', ayuda: 'Muestra todos los productos de una marca.' },
    { valor: 'busqueda', etiqueta: 'Búsqueda', ayuda: 'Muestra los resultados de buscar un texto (ej: gamer).' },
    { valor: 'url', etiqueta: 'Enlace manual', ayuda: 'Una ruta de la tienda (/...) o una página externa (https://...).' },
  ];

  productos: OpcionDestino[] = [];
  categorias: OpcionDestino[] = [];
  marcas: OpcionDestino[] = [];
  campanias: OpcionDestino[] = [];
  productoSearch = '';

  get tipo(): TipoDestino {
    return this.form.get('sld_destino_tipo')?.value ?? null;
  }

  get ayuda(): string {
    return this.tipos.find((t) => t.valor === this.tipo)?.ayuda ?? '';
  }

  get usaSelect(): boolean {
    return CON_ID.includes(this.tipo as string);
  }

  get opciones(): OpcionDestino[] {
    switch (this.tipo) {
      case 'producto': return this.productos;
      case 'categoria': return this.categorias;
      case 'marca': return this.marcas;
      case 'campania': return this.campanias;
      default: return [];
    }
  }

  /** Vista previa: misma regla que Slider::enlaceTienda() en el backend. */
  get enlaceGenerado(): string | null {
    const id = this.form.value.sld_destino_id;
    const valor = String(this.form.value.sld_destino_valor ?? '').trim();

    switch (this.tipo) {
      case 'producto': {
        const p = this.productos.find((x) => x.id == id);
        return p?.slug ? `/producto/${p.slug}` : null;
      }
      case 'categoria': return id ? `/productos-busqueda?categoria=${id}` : null;
      case 'marca': return id ? `/productos-busqueda?marca=${id}` : null;
      case 'campania': return id ? `/productos-busqueda?campania=${id}` : null;
      case 'busqueda': return valor ? `/productos-busqueda?search=${encodeURIComponent(valor)}` : null;
      case 'url': return valor || null;
      default: return null;
    }
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    const productoActual = this.tipo === 'producto' ? this.form.value.sld_destino_id : null;

    this.sliderService.destinos(this.productoSearch, productoActual).subscribe((resp: any) => {
      this.productos = resp.productos ?? [];
      this.categorias = resp.categorias ?? [];
      this.marcas = resp.marcas ?? [];
      this.campanias = resp.campanias ?? [];
    });
  }

  elegirTipo(tipo: TipoDestino): void {
    // Al cambiar de tipo se limpian los datos del tipo anterior
    this.form.patchValue({ sld_destino_tipo: tipo, sld_destino_id: null, sld_destino_valor: '' });
  }
}
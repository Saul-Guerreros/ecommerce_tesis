import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../config/config';
import {
  ColorDisponible,
  EspecificacionDetalle,
  ProductoDetalle,
} from '../../core/models/producto-detalle.model';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { ProductoDetalleService } from '../../core/services/producto-detalle.service';

type TabDetalle = 'descripcion' | 'especificaciones';

@Component({
  selector: 'app-producto-detalle',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule],
  templateUrl: './producto-detalle.component.html',
  styleUrl: './producto-detalle.component.css',
})
export class ProductoDetalleComponent implements OnInit {
  cargando = true;
  noEncontrado = false;

  producto: ProductoDetalle | null = null;
  descripcion: string | null = null;
  galeria: string[] = [];
  especificaciones: EspecificacionDetalle[] = [];
  coloresDisponibles: ColorDisponible[] = [];
  relacionados: ProductoVitrina[] = [];

  slugActual: string | null = null;
  imagenActivaIndex = 0;
  cantidad = 1;
  tabActiva: TabDetalle = 'descripcion';

  favoritoActivo = false;
  compararActivo = false;

  // Mismo placeholder que usa el home cuando un producto todavía no
  // tiene imagen cargada, para que el detalle se vea consistente.
  readonly imagenFallback =
    'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22600%22%20height%3D%22450%22%20viewBox%3D%220%200%20600%20450%22%3E%3Crect%20width%3D%22600%22%20height%3D%22450%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22300%22%20y%3D%22235%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2222%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';

  constructor(
    private route: ActivatedRoute,
    private productoDetalleService: ProductoDetalleService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    // paramMap (no snapshot) para que al navegar de un color/relacionado a
    // otro producto sin salir de esta misma ruta, Angular reutilice el
    // componente pero vuelva a cargar todo con el slug nuevo.
    this.route.paramMap.subscribe((params) => {
      const slug = params.get('slug');
      if (slug) {
        this.cargarProducto(slug);
      }
    });
  }

  private cargarProducto(slug: string): void {
    // Se resetea todo (incluido producto = null) para que el *ngIf del
    // template desmonte y vuelva a montar la galería/relacionados desde
    // cero con cada producto nuevo, en vez de mutar el mismo DOM.
    this.cargando = true;
    this.noEncontrado = false;
    this.producto = null;
    this.slugActual = slug;

    this.productoDetalleService.obtener(slug).subscribe({
      next: (res) => {
        this.producto = res.data.producto;
        this.descripcion = res.data.descripcion;
        this.galeria = res.data.galeria ?? [];
        this.especificaciones = res.data.especificaciones ?? [];
        this.coloresDisponibles = res.data.colores_disponibles ?? [];
        this.relacionados = res.data.relacionados ?? [];

        this.imagenActivaIndex = 0;
        this.cantidad = 1;
        this.favoritoActivo = false;
        this.compararActivo = false;
        this.tabActiva = 'descripcion';
        this.cargando = false;

        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior });
        }
      },
      error: () => {
        this.cargando = false;
        this.noEncontrado = true;
      },
    });
  }

  // ---------------------------------------------------------------
  // Imágenes
  // ---------------------------------------------------------------

  /** Resuelve URL absoluta igual que el resto del sitio (ver home.component). */
  private resolverImagen(url: string | null | undefined): string {
    if (!url) {
      return this.imagenFallback;
    }
    if (url.startsWith('http') || url.startsWith('data:')) {
      return url;
    }
    return `${URL_BACKEND.replace(/\/$/, '')}/${url.replace(/^\//, '')}`;
  }

  get imagenActual(): string {
    if (this.galeria.length > 0) {
      return this.resolverImagen(this.galeria[this.imagenActivaIndex] ?? this.galeria[0]);
    }
    return this.resolverImagen(this.producto?.imagen ?? null);
  }

  imagenMiniatura(url: string): string {
    return this.resolverImagen(url);
  }

  imagenColor(color: ColorDisponible): string {
    return this.resolverImagen(color.imagen);
  }

  imagenRelacionado(producto: ProductoVitrina): string {
    return this.resolverImagen(producto.imagen);
  }

  seleccionarImagen(indice: number): void {
    this.imagenActivaIndex = indice;
  }

  // ---------------------------------------------------------------
  // Stock y cantidad
  // ---------------------------------------------------------------

  /** null = no controla stock (siempre disponible según el inventario). */
  get stockDisponible(): number | null {
    if (!this.producto?.controla_stock) {
      return null;
    }
    return this.producto.stock ?? 0;
  }

  get sinStock(): boolean {
    return this.stockDisponible !== null && this.stockDisponible <= 0;
  }

  incrementarCantidad(): void {
    const max = this.stockDisponible;
    if (max !== null && this.cantidad >= max) {
      return;
    }
    this.cantidad++;
  }

  decrementarCantidad(): void {
    if (this.cantidad > 1) {
      this.cantidad--;
    }
  }

  get subtotal(): number {
    return (this.producto?.precio_final ?? 0) * this.cantidad;
  }

  // ---------------------------------------------------------------
  // Acciones
  // ---------------------------------------------------------------

  agregarAlCarrito(): void {
    if (!this.producto || this.sinStock) {
      return;
    }
    // Todavía no hay un servicio de carrito real conectado al backend:
    // por ahora esto solo da feedback visual, igual que el resto del
    // sitio (los botones "add to cart" del home tampoco persisten nada).
    this.toastr.success(
      `${this.cantidad} × ${this.producto.titulo}`,
      'Added to cart'
    );
  }

  toggleFavorito(): void {
    this.favoritoActivo = !this.favoritoActivo;
    if (this.favoritoActivo) {
      this.toastr.info(this.producto?.titulo ?? '', 'Added to favorites');
    }
  }

  toggleComparar(): void {
    this.compararActivo = !this.compararActivo;
    if (this.compararActivo) {
      this.toastr.info(this.producto?.titulo ?? '', 'Added to compare');
    }
  }

  seleccionarTab(tab: TabDetalle): void {
    this.tabActiva = tab;
  }

  esColorActivo(color: ColorDisponible): boolean {
    return !!color.slug && color.slug === this.slugActual;
  }

  /**
   * colores_disponibles solo trae los OTROS colores (el backend excluye a
   * propósito el producto actual). Para pintar la fila completa de swatches
   * con el activo incluido, se antepone el propio producto como una opción
   * más, marcada como tal.
   */
  get coloresParaMostrar(): ColorDisponible[] {
    if (!this.producto || this.coloresDisponibles.length === 0) {
      return [];
    }
    const actual: ColorDisponible = {
      slug: this.producto.slug,
      titulo: this.producto.titulo,
      imagen: this.producto.imagen,
    };
    return [actual, ...this.coloresDisponibles];
  }

  // ---------------------------------------------------------------
  // trackBy
  // ---------------------------------------------------------------

  trackByIndice(indice: number): number {
    return indice;
  }

  trackByColor(_indice: number, color: ColorDisponible): string {
    return color.slug ?? color.titulo;
  }

  trackByRelacionado(_indice: number, producto: ProductoVitrina): number {
    return producto.pub_id;
  }
}

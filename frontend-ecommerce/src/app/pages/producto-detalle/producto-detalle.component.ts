import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, HostListener, OnInit } from '@angular/core';
import { Router, ActivatedRoute, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../config/config';
import { CuoteroComponent } from '../../shared/components/cuotero/cuotero.component';
import {
  ColorDisponible,
  EspecificacionDetalle,
  ProductoDetalle,
} from '../../core/models/producto-detalle.model';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { ProductoDetalleService } from '../../core/services/producto-detalle.service';

import { AuthService } from '../auth/service/auth.service';
import { CarritoService } from '../../core/services/carrito.service';

type TabDetalle = 'descripcion' | 'especificaciones' | 'resenas';

@Component({
  selector: 'app-producto-detalle',
  standalone: true,
   imports: [CommonModule, CurrencyPipe, RouterModule, CuoteroComponent],
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
  sucursalesPickup: string[] = [];

  slugActual: string | null = null;
  imagenActivaIndex = 0;
  cantidad = 1;
  tabActiva: TabDetalle = 'descripcion';

  favoritoActivo = false;
  compararActivo = false;

  // Visor de zoom propio (no depende de uk-lightbox, que en este proyecto
  // no reacciona de forma confiable sobre nodos que Angular inserta
  // dinámicamente después de que UIkit ya arrancó).
  zoomAbierto = false;

  readonly imagenFallback =
    'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22600%22%20height%3D%22450%22%20viewBox%3D%220%200%20600%20450%22%3E%3Crect%20width%3D%22600%22%20height%3D%22450%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22300%22%20y%3D%22235%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2222%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private productoDetalleService: ProductoDetalleService,
    private toastr: ToastrService,
    private carritoService: CarritoService,
    private authService: AuthService,
    
  ) {}

  agregando = false;
  ngOnInit(): void {
    this.route.paramMap.subscribe((params) => {
      const slug = params.get('slug');
      if (slug) {
        this.cargarProducto(slug);
      }
    });
  }

  private cargarProducto(slug: string): void {
    this.cargando = true;
    this.noEncontrado = false;
    this.producto = null;
    this.slugActual = slug;
    this.zoomAbierto = false;

    this.productoDetalleService.obtener(slug).subscribe({
      next: (res) => {
        this.producto = res.data.producto;
        this.descripcion = res.data.descripcion;
        this.galeria = res.data.galeria ?? [];
        this.especificaciones = res.data.especificaciones ?? [];
        this.coloresDisponibles = res.data.colores_disponibles ?? [];
        this.relacionados = res.data.relacionados ?? [];
        this.sucursalesPickup = res.data.sucursales_pickup ?? [];

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
         error: (err) => {
        console.error('Error cargando producto:', err);
        this.cargando = false;
        this.noEncontrado = true;
      },
    });
  }

  // ---------------------------------------------------------------
  // Imágenes / zoom
  // ---------------------------------------------------------------

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

  abrirZoom(): void {
    this.zoomAbierto = true;
  }

  cerrarZoom(): void {
    this.zoomAbierto = false;
  }

  zoomAnterior(): void {
    if (this.galeria.length < 2) {
      return;
    }
    this.imagenActivaIndex =
      (this.imagenActivaIndex - 1 + this.galeria.length) % this.galeria.length;
  }

  zoomSiguiente(): void {
    if (this.galeria.length < 2) {
      return;
    }
    this.imagenActivaIndex = (this.imagenActivaIndex + 1) % this.galeria.length;
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.zoomAbierto) {
      this.cerrarZoom();
    }
  }

  // ---------------------------------------------------------------
  // Stock y cantidad
  // ---------------------------------------------------------------

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

  get disponibilidadDetalle(): string {
    if (this.stockDisponible !== null) {
      return `${this.stockDisponible} unit${this.stockDisponible === 1 ? '' : 's'} available`;
    }
    return 'Ready to order';
  }

  // ---------------------------------------------------------------
  // Descripción / especificaciones (resumen)
  // ---------------------------------------------------------------

  get parrafosDescripcion(): string[] {
    if (!this.descripcion) {
      return [];
    }
    return this.descripcion
      .split(/\r?\n+/)
      .map((p) => p.trim())
      .filter((p) => p.length > 0);
  }

  get especificacionesResumen(): EspecificacionDetalle[] {
    return this.especificaciones.slice(0, 4);
  }

  // ---------------------------------------------------------------
  // Acciones
  // ---------------------------------------------------------------

agregarAlCarrito(): void {
  if (!this.producto || this.sinStock || this.agregando) {
    return;
  }

  // El carritoService ya resuelve internamente si hay sesión (BD) o es
  // invitado (localStorage): no hace falta pedir login para agregar.
  // El login solo se exige en /proceso-de-pago.
  this.agregando = true;

  this.carritoService
    .agregar({
      car_idpro: this.producto.prd_id,
      car_idvarp: null, // los colores son productos separados, no variaciones
      car_cantid: this.cantidad,
    })
    .subscribe({
      next: (resp) => {
        this.agregando = false;
        this.toastr.success(resp.message, `${this.cantidad} × ${this.producto!.titulo}`);
      },
      error: (err) => {
        this.agregando = false;
        this.toastr.error(this.mensajeError(err), 'No se pudo agregar');
      },
    });
}

/**
 * Extrae el texto del primer error 422 que manda Laravel
 * ({ errors: { car_cantid: ["No hay stock suficiente..."] } }).
 */
private mensajeError(err: any): string {
  const errores = err?.error?.errors;
  if (errores) {
    const primerCampo = Object.keys(errores)[0];
    return errores[primerCampo]?.[0] ?? 'Intentá nuevamente.';
  }
  return err?.error?.message ?? 'Intentá nuevamente.';
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

  verEspecificacionesCompletas(): void {
    this.seleccionarTab('especificaciones');
  }

  esColorActivo(color: ColorDisponible): boolean {
    return !!color.slug && color.slug === this.slugActual;
  }

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
import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { URL_BACKEND } from '../../config/config';
import { ConfigFiltros } from '../../core/models/filtro-productos.model';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { FiltroProductosService } from '../../core/services/filtro-productos.service';
import { MonedaPyPipe } from '../../shared/pipes/moneda-py.pipe';

type Orden = 'nuevos' | 'precio_asc' | 'precio_desc';

@Component({
  selector: 'app-productos-busqueda',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, MonedaPyPipe],
  templateUrl: './productos-busqueda.component.html',
  styleUrl: './productos-busqueda.component.css',
})
export class ProductosBusquedaComponent implements OnInit, OnDestroy {
  cargandoFiltros = true;
  cargandoProductos = true;

  config: ConfigFiltros | null = null;
  productos: ProductoVitrina[] = [];

  categoriasSeleccionadas: number[] = [];
  marcasSeleccionadas: number[] = [];
  precioMin: number | null = null;
  precioMax: number | null = null;
  soloOferta = false;
  orden: Orden = 'nuevos';
  busqueda = '';

  paginaActual = 1;
  porPagina = 12;
  totalResultados = 0;
  ultimaPagina = 1;
  paginas: number[] = [];

  // Mismo fallback que usa home.component.ts, para consistencia visual.
  readonly imagenFallback =
    'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22300%22%20height%3D%22225%22%20viewBox%3D%220%200%20300%20225%22%3E%3Crect%20width%3D%22300%22%20height%3D%22225%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22150%22%20y%3D%22117%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2216%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';

  private busquedaChange$ = new Subject<void>();
  private subs = new Subscription();

  constructor(
    private route: ActivatedRoute,
    private filtroService: FiltroProductosService,
  ) {}

  ngOnInit(): void {
    // El buscador del header manda ?search=... por query string.
    this.busqueda = this.route.snapshot.queryParamMap.get('search') ?? '';

    const categoriaParam = this.route.snapshot.queryParamMap.get('categoria');
    if (categoriaParam) {
      this.categoriasSeleccionadas = [Number(categoriaParam)];
    }

    this.subs.add(
      this.busquedaChange$.pipe(debounceTime(400)).subscribe(() => {
        this.paginaActual = 1;
        this.buscarProductos();
      }),
    );

    this.cargarConfigFiltros();
    this.buscarProductos();
  }

  ngOnDestroy(): void {
    this.subs.unsubscribe();
  }

  private cargarConfigFiltros(): void {
    this.cargandoFiltros = true;
    this.filtroService.configFiltros().subscribe({
      next: (resp) => {
        this.config = resp.data;
        this.cargandoFiltros = false;
      },
      error: () => (this.cargandoFiltros = false),
    });
  }

  buscarProductos(): void {
    this.cargandoProductos = true;
    this.filtroService
      .buscar({
        categorias: this.categoriasSeleccionadas,
        marcas: this.marcasSeleccionadas,
        precio_min: this.precioMin,
        precio_max: this.precioMax,
        en_oferta: this.soloOferta,
        orden: this.orden,
        busqueda: this.busqueda,
        page: this.paginaActual,
        por_pagina: this.porPagina,
      })
      .subscribe({
        next: (resp) => {
          this.productos = resp.data;
          this.totalResultados = resp.meta.total;
          this.ultimaPagina = resp.meta.ultima_pagina;
          this.paginas = Array.from({ length: this.ultimaPagina }, (_, i) => i + 1);
          this.cargandoProductos = false;
        },
        error: () => {
          this.productos = [];
          this.cargandoProductos = false;
        },
      });
  }

  onBusquedaChange(): void {
    this.busquedaChange$.next();
  }

  onFiltroChange(): void {
    this.paginaActual = 1;
    this.buscarProductos();
  }

  toggleCategoria(catId: number): void {
    const i = this.categoriasSeleccionadas.indexOf(catId);
    i !== -1 ? this.categoriasSeleccionadas.splice(i, 1) : this.categoriasSeleccionadas.push(catId);
    this.onFiltroChange();
  }

  toggleMarca(marId: number): void {
    const i = this.marcasSeleccionadas.indexOf(marId);
    i !== -1 ? this.marcasSeleccionadas.splice(i, 1) : this.marcasSeleccionadas.push(marId);
    this.onFiltroChange();
  }

  limpiarFiltros(): void {
    this.categoriasSeleccionadas = [];
    this.marcasSeleccionadas = [];
    this.precioMin = null;
    this.precioMax = null;
    this.soloOferta = false;
    this.busqueda = '';
    this.orden = 'nuevos';
    this.onFiltroChange();
  }

  irAPagina(pagina: number): void {
    if (pagina < 1 || pagina > this.ultimaPagina) return;
    this.paginaActual = pagina;
    this.buscarProductos();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // Mismo criterio que home.component.ts: el backend manda URL absoluta,
  // pero por las dudas se resuelve acá también.
  imagenVitrina(producto: ProductoVitrina): string {
    if (!producto.imagen) return this.imagenFallback;
    if (producto.imagen.startsWith('http')) return producto.imagen;
    return `${URL_BACKEND.replace(/\/$/, '')}/${producto.imagen.replace(/^\//, '')}`;
  }

  trackByVitrina(_index: number, producto: ProductoVitrina): number {
    return producto.pub_id;
  }
}
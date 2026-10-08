import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { URL_BACKEND } from '../../config/config';
import { ConfigFiltros, MetaBusqueda } from '../../core/models/filtro-productos.model';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { FiltroProductosService } from '../../core/services/filtro-productos.service';
import { MonedaPyPipe } from '../../shared/pipes/moneda-py.pipe';
import { CargandoIaComponent } from '../../shared/components/cargando-ia/cargando-ia.component';

type Orden = 'relevancia' | 'nuevos' | 'precio_asc' | 'precio_desc';

@Component({
  selector: 'app-productos-busqueda',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, MonedaPyPipe, CargandoIaComponent],
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
  orden: Orden = 'relevancia';
  busqueda = '';
  /** "Mostrando resultados para notebook" cuando el buscador corrigió la consulta. */
  consultaCorregida: string | null = null;
  /** Lo que entendió la IA ("Notebooks con 16 GB de RAM o más..."). */
  interpretacion: MetaBusqueda['interpretacion'] = null;

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
    /** Aviso "Procesando búsqueda con IA" (ver CargandoIaComponent). */
  procesandoIA = false;
  modoAvisoIA: 'pantalla' | 'en-linea' = 'pantalla';
  /** Peticiones en curso: una búsqueda nueva cancela la anterior. */
  private busquedaSub?: Subscription;
  private analisisSub?: Subscription;

  constructor(
    private route: ActivatedRoute,
    private filtroService: FiltroProductosService,
  ) {}

  ngOnInit(): void {
    this.subs.add(
      this.busquedaChange$.pipe(debounceTime(400)).subscribe(() => {
        this.paginaActual = 1;
        // Viene del buscador del header: aviso a pantalla completa.
        this.buscarProductos('url');
      }),
    );

    // El buscador del header navega con ?search=... SIN recargar la página.
    // Si el cliente ya está en esta página y busca otra cosa, Angular
    // reutiliza el componente: por eso se escucha el query string en vez
    // de leerlo una sola vez (snapshot).
    this.subs.add(
      this.route.queryParamMap.subscribe((params) => {
        this.busqueda = params.get('search') ?? '';

        const categoriaParam = params.get('categoria');
        this.categoriasSeleccionadas = categoriaParam ? [Number(categoriaParam)] : [];

        this.paginaActual = 1;
        this.buscarProductos('url');
      }),
    );

    this.cargarConfigFiltros();
  }

    ngOnDestroy(): void {
    this.subs.unsubscribe();
    this.busquedaSub?.unsubscribe();
    this.analisisSub?.unsubscribe();
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

    /**
   * @param origen 'url' = llegó desde el header (aviso a pantalla completa);
   *               'pagina' = filtros o buscador de esta página (aviso en línea,
   *               para no tapar el campo mientras el cliente escribe).
   */
  buscarProductos(origen: 'url' | 'pagina' = 'pagina'): void {
    this.busquedaSub?.unsubscribe();
    this.analisisSub?.unsubscribe();
    this.cargandoProductos = true;
    this.procesandoIA = false;

    // Se pregunta primero (y en paralelo) si la búsqueda usará IA: si es así,
    // se muestra el aviso mientras la búsqueda real sigue en curso.
    // Solo en la página 1: al paginar, la interpretación ya está en caché.
    const texto = this.busqueda.trim();
    if (texto && this.paginaActual === 1) {
      this.analisisSub = this.filtroService.analizarBusqueda(texto).subscribe({
        next: (r) => {
          if (r.usara_ia && this.cargandoProductos) {
            this.modoAvisoIA = origen === 'url' ? 'pantalla' : 'en-linea';
            this.procesandoIA = true;
          }
        },
        error: () => {}, // sin aviso: la búsqueda sigue igual
      });
    }

    this.busquedaSub = this.filtroService
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
          this.consultaCorregida = resp.meta.consulta_corregida ?? null;
          this.interpretacion = resp.meta.interpretacion ?? null;
          this.ultimaPagina = resp.meta.ultima_pagina;
          this.paginas = Array.from({ length: this.ultimaPagina }, (_, i) => i + 1);
          this.cargandoProductos = false;
          this.procesandoIA = false;
        },
        error: () => {
          this.productos = [];
          this.consultaCorregida = null;
          this.interpretacion = null;
          this.cargandoProductos = false;
          this.procesandoIA = false;
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
    this.orden = 'relevancia';
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
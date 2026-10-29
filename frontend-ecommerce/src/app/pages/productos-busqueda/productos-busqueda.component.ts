import { CommonModule, DOCUMENT } from '@angular/common';
import { Component, HostListener, Inject, OnDestroy, OnInit, Renderer2 } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { URL_BACKEND } from '../../config/config';
import { ConfigFiltros, MetaBusqueda } from '../../core/models/filtro-productos.model';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { FiltroProductosService } from '../../core/services/filtro-productos.service';
import { MonedaPyPipe } from '../../shared/pipes/moneda-py.pipe';
import { CargandoIaComponent } from '../../shared/components/cargando-ia/cargando-ia.component';
import { Router } from '@angular/router';
import { ProductoCardComponent } from '../../shared/components/producto-card/producto-card.component';
import { ActividadTiendaService } from '../../core/services/actividad-tienda.service';

type Orden = 'relevancia' | 'nuevos' | 'precio_asc' | 'precio_desc';

type TipoChip = 'categoria' | 'marca' | 'precio' | 'oferta';

/** Filtro aplicado que se muestra como "chip" removible. */
interface ChipFiltro {
  clave: string;
  tipo: TipoChip;
  id?: number;
  etiqueta: string;
}

@Component({
  selector: 'app-productos-busqueda',
  standalone: true,
    imports: [CommonModule, FormsModule, RouterModule, CargandoIaComponent, ProductoCardComponent],
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
    /** Viene de un banner de campaña (?campania=ID). */
  campaniaId: number | null = null;
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
    private actividad: ActividadTiendaService,
    private router: Router,
    private renderer: Renderer2,
    @Inject(DOCUMENT) private document: Document,
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

        const marcaParam = params.get('marca');
        this.marcasSeleccionadas = marcaParam ? [Number(marcaParam)] : [];

        const campaniaParam = params.get('campania');
        this.campaniaId = campaniaParam ? Number(campaniaParam) : null;

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
    this.cerrarFiltros(); // libera el scroll del body si el panel quedó abierto
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
        campania: this.campaniaId,
      })
      .subscribe({
        next: (resp) => {
          this.productos = resp.data;
          this.totalResultados = resp.meta.total;
          this.consultaCorregida = resp.meta.consulta_corregida ?? null;
          this.interpretacion = resp.meta.interpretacion ?? null;
          this.actividad.marcarBusqueda(resp.meta.busqueda_id);
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

  trackByVitrina(_index: number, producto: ProductoVitrina): number {
    return producto.pub_id;
  }

  /** Quita el filtro de campaña y muestra el catálogo completo. */
  quitarCampania(): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { campania: null },
      queryParamsHandling: 'merge',
    });
  }

    // ---------------------------------------------------------------
  // Panel de filtros en móvil (bottom sheet)
  // ---------------------------------------------------------------

  filtrosAbiertos = false;
  private readonly monedaPy = new MonedaPyPipe();

  abrirFiltros(): void {
    this.filtrosAbiertos = true;
    // Evita que la página de fondo se desplace mientras el panel está abierto
    this.renderer.setStyle(this.document.body, 'overflow', 'hidden');
  }

  cerrarFiltros(): void {
    if (!this.filtrosAbiertos) return;
    this.filtrosAbiertos = false;
    this.renderer.removeStyle(this.document.body, 'overflow');
  }

  @HostListener('document:keydown.escape')
  alPresionarEscape(): void {
    this.cerrarFiltros();
  }

  /** Si se gira el dispositivo o se agranda la ventana a escritorio, se cierra. */
  @HostListener('window:resize')
  alRedimensionar(): void {
    if (this.filtrosAbiertos && window.innerWidth >= 960) {
      this.cerrarFiltros();
    }
  }

  private tieneValor(valor: number | null | undefined): boolean {
    return valor !== null && valor !== undefined && (valor as unknown) !== '';
  }

  get cantidadFiltrosActivos(): number {
    const precio = this.tieneValor(this.precioMin) || this.tieneValor(this.precioMax) ? 1 : 0;
    return (
      this.categoriasSeleccionadas.length +
      this.marcasSeleccionadas.length +
      precio +
      (this.soloOferta ? 1 : 0)
    );
  }

  /** Filtros aplicados como chips removibles. */
  get chipsFiltros(): ChipFiltro[] {
    const chips: ChipFiltro[] = [];

    for (const id of this.categoriasSeleccionadas) {
      const cat = this.config?.categorias.find((c) => c.cat_id === id);
      chips.push({ clave: `cat-${id}`, tipo: 'categoria', id, etiqueta: cat?.cat_nombre ?? 'Categoría' });
    }

    for (const id of this.marcasSeleccionadas) {
      const marca = this.config?.marcas.find((m) => m.mar_id === id);
      chips.push({ clave: `mar-${id}`, tipo: 'marca', id, etiqueta: marca?.mar_descri ?? 'Marca' });
    }

    const hayMin = this.tieneValor(this.precioMin);
    const hayMax = this.tieneValor(this.precioMax);
    if (hayMin || hayMax) {
      const min = this.monedaPy.transform(this.precioMin);
      const max = this.monedaPy.transform(this.precioMax);
      const etiqueta = hayMin && hayMax ? `${min} – ${max}` : hayMin ? `Desde ${min}` : `Hasta ${max}`;
      chips.push({ clave: 'precio', tipo: 'precio', etiqueta });
    }

    if (this.soloOferta) {
      chips.push({ clave: 'oferta', tipo: 'oferta', etiqueta: 'En oferta' });
    }

    return chips;
  }

  quitarChip(chip: ChipFiltro): void {
    switch (chip.tipo) {
      case 'categoria':
        this.categoriasSeleccionadas = this.categoriasSeleccionadas.filter((id) => id !== chip.id);
        break;
      case 'marca':
        this.marcasSeleccionadas = this.marcasSeleccionadas.filter((id) => id !== chip.id);
        break;
      case 'precio':
        this.precioMin = null;
        this.precioMax = null;
        break;
      case 'oferta':
        this.soloOferta = false;
        break;
    }
    this.onFiltroChange();
  }
}
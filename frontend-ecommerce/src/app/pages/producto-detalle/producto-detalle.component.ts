import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, ElementRef, HostListener, OnInit, ViewChild } from '@angular/core';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../config/config';
import { SelectorCantidadComponent } from '../../shared/components/selector-cantidad/selector-cantidad.component';
import {
  ColorDisponible,
  EspecificacionDetalle,
  OpcionesCompra,
  ProductoDetalle,
  StockAvisos,
  TramoPrecio,
} from '../../core/models/producto-detalle.model';
import { CuotaOpcion, ProductoVitrina } from '../../core/models/vitrina.model';
import { LineaContado, SolicitudCredito } from '../../core/models/solicitud-compra.model';
import { ProductoDetalleService } from '../../core/services/producto-detalle.service';

import { AuthService } from '../auth/service/auth.service';
import { CarritoService } from '../../core/services/carrito.service';
import { CarritoUiService } from '../../core/services/carrito-ui.service';
import { SolicitudCreditoService } from '../../core/services/solicitud-credito.service';


type TabDetalle = 'descripcion' | 'especificaciones' | 'resenas';

/** Opción de cuotas del backend + la cuota por la cantidad elegida. */
interface CuotaVista extends CuotaOpcion {
  monto_cuota_total: number;
}

@Component({
  selector: 'app-producto-detalle',
  standalone: true,
     imports: [CommonModule, CurrencyPipe, RouterModule, SelectorCantidadComponent],
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
  tabActiva: TabDetalle = 'descripcion';

  // --- Compra (Contado / Cuotas) ---------------------------------------
  // Las dos cantidades son INDEPENDIENTES a propósito: cada modalidad es
  // una acción de compra distinta. Nunca se sincronizan entre sí.
  compra: OpcionesCompra | null = null;
  stockAvisos: StockAvisos | null = null;
  cantidadContado = 1;
  cantidadCuotas = 1;
  /** Nº de cuotas elegido (3, 6, 12...). Se guarda el número, no el objeto,
   *  para conservar la elección cuando el selector cambia de tramo. */
  private cuotaElegidaN: number | null = null;

  favoritoActivo = false;
  compararActivo = false;

  // Visor de zoom propio (no depende de uk-lightbox, que en este proyecto
  // no reacciona de forma confiable sobre nodos que Angular inserta
  // dinámicamente después de que UIkit ya arrancó).
  zoomAbierto = false;

  readonly imagenFallback =
    'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22600%22%20height%3D%22450%22%20viewBox%3D%220%200%20600%20450%22%3E%3Crect%20width%3D%22600%22%20height%3D%22450%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22300%22%20y%3D%22235%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2222%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';

	  // Carrusel vertical de miniaturas (en móvil pasa a horizontal por CSS).
  // hayDesborde: hay más miniaturas de las que caben -> se muestran las flechas.
  hayDesborde = false;
  puedeAnterior = false;
  puedeSiguiente = false;
  private miniaturasEl: HTMLElement | null = null;

  // Setter en vez de un @ViewChild "normal": el contenedor vive dentro de
  // *ngIf (solo existe cuando ya cargó el producto y hay más de 1 imagen).
  @ViewChild('thumbsViewport')
  set thumbsViewport(ref: ElementRef<HTMLElement> | undefined) {
    this.miniaturasEl = ref?.nativeElement ?? null;
    if (this.miniaturasEl) {
      // Diferido para no cambiar estado en medio de la detección de cambios.
      setTimeout(() => this.actualizarFlechasMiniaturas());
    }
  }
  constructor(
    private route: ActivatedRoute,
    private productoDetalleService: ProductoDetalleService,
    private toastr: ToastrService,
    private carritoService: CarritoService,
    private authService: AuthService,
    private carritoUi: CarritoUiService,
    private solicitudCredito: SolicitudCreditoService,
  ) {}

  agregando = false;
  botonAgregando: 'cuotas' | 'contado' | null = null;
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
        this.compra = res.data.compra ?? null;
        this.stockAvisos = res.data.stock_avisos ?? null;

        this.imagenActivaIndex = 0;
        // Ambas cantidades arrancan en el mínimo de compra: 1, o el mínimo
        // mayorista si el producto se vende solo al por mayor.
        this.cantidadContado = this.cantidadMinima;
        this.cantidadCuotas = this.cantidadMinima;
        this.cuotaElegidaN = null;
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
	this.mostrarMiniaturaActiva();
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
	this.mostrarMiniaturaActiva();
  }

  zoomSiguiente(): void {
    if (this.galeria.length < 2) {
      return;
    }
    this.imagenActivaIndex = (this.imagenActivaIndex + 1) % this.galeria.length;
	 this.mostrarMiniaturaActiva();
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.zoomAbierto) {
      this.cerrarZoom();
    }
  }
  
    // ---------------------------------------------------------------
  // Carrusel de miniaturas
  // ---------------------------------------------------------------

  /** true si el carrusel corre en horizontal (móvil), false si es vertical. */
  private get miniaturasHorizontales(): boolean {
    return !!this.miniaturasEl && getComputedStyle(this.miniaturasEl).flexDirection === 'row';
  }

  /** Recalcula si hacen falta flechas y cuáles están habilitadas. */
  actualizarFlechasMiniaturas(): void {
    const el = this.miniaturasEl;
    if (!el || typeof window === 'undefined') {
      return;
    }
    const horizontal = this.miniaturasHorizontales;
    const visible = horizontal ? el.clientWidth : el.clientHeight;
    const total = horizontal ? el.scrollWidth : el.scrollHeight;
    const posicion = horizontal ? el.scrollLeft : el.scrollTop;

    this.hayDesborde = total > visible + 1;
    this.puedeAnterior = posicion > 1;
    this.puedeSiguiente = posicion + visible < total - 1;
  }

  /** Flecha: avanza (1) o retrocede (-1) casi una "página" de miniaturas. */
  desplazarMiniaturas(direccion: 1 | -1): void {
    const el = this.miniaturasEl;
    if (!el) {
      return;
    }
    const horizontal = this.miniaturasHorizontales;
    const visible = horizontal ? el.clientWidth : el.clientHeight;
    const paso = direccion * Math.max(visible * 0.8, 100);

    if (horizontal) {
      el.scrollBy({ left: paso, behavior: 'smooth' });
    } else {
      el.scrollBy({ top: paso, behavior: 'smooth' });
    }
  }

  /** Si la miniatura activa quedó fuera de la zona visible, la trae a la vista. */
  private mostrarMiniaturaActiva(): void {
    const el = this.miniaturasEl;
    const miniatura = el?.children[this.imagenActivaIndex] as HTMLElement | undefined;
    if (!el || !miniatura) {
      return;
    }
    const caja = el.getBoundingClientRect();
    const item = miniatura.getBoundingClientRect();

    let dy = 0;
    if (item.top < caja.top) {
      dy = item.top - caja.top;
    } else if (item.bottom > caja.bottom) {
      dy = item.bottom - caja.bottom;
    }

    let dx = 0;
    if (item.left < caja.left) {
      dx = item.left - caja.left;
    } else if (item.right > caja.right) {
      dx = item.right - caja.right;
    }

    if (dx !== 0 || dy !== 0) {
      el.scrollBy({ left: dx, top: dy, behavior: 'smooth' });
    }
  }

  @HostListener('window:resize')
  onResize(): void {
    this.actualizarFlechasMiniaturas();
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

  // ---------------------------------------------------------------
  // Compra: tramo de precio, cuotas y límites de cantidad
  //
  // Orden de la regla (ver instrucciones, punto 14): primero se determina
  // el TRAMO de precio (minorista / mayorista) según la cantidad y recién
  // después se toman las cuotas de ese tramo. El backend ya entrega ambos
  // tramos con sus cuotas calculadas; acá solo se elige, no se calcula.
  // ---------------------------------------------------------------

  /** Piso de ambos controles de unidades. */
  get cantidadMinima(): number {
    return Math.max(1, this.compra?.cantidad_minima ?? 1);
  }

  /** Tope por stock; null = el producto no controla stock. */
  get stockMaximo(): number | null {
    const stock = this.stockDisponible;
    return stock === null ? null : Math.floor(stock);
  }

  /** Producto con al menos un tramo de precio vendible. */
  get tieneOpcionDeCompra(): boolean {
    return !!(this.compra?.minorista || this.compra?.mayorista);
  }

  /** El stock no alcanza ni para la compra mínima (caso solo mayorista). */
  get stockInsuficienteParaMinimo(): boolean {
    const max = this.stockMaximo;
    return max !== null && max > 0 && max < this.cantidadMinima;
  }

  // --- Avisos de stock (configurables desde el panel admin) ---

  /** "Solo quedan X unidades": null si no corresponde mostrarlo. */
  get mensajePocoStock(): string | null {
    const avisos = this.stockAvisos;

    if (!avisos?.poco_stock || avisos.unidades === null || this.sinStock) {
      return null;
    }

    return avisos.unidades === 1
      ? 'Solo queda 1 unidad'
      : `Solo quedan ${avisos.unidades} unidades`;
  }

    /**
   * Se pidieron tantas unidades como hay disponibles (el selector ya
   * clampeó cualquier valor mayor al stock real). Esto NO es una táctica
   * de venta como "poco stock": es manejo de error, así que siempre se
   * evalúa igual, sin depender de ninguna configuración del admin.
   */
  private alMaximo(cantidad: number): boolean {
    const max = this.stockMaximo;

    return max !== null && max >= this.cantidadMinima && cantidad >= max;
  }

  get alMaximoCuotas(): boolean {
    return this.alMaximo(this.cantidadCuotas);
  }

  get alMaximoContado(): boolean {
    return this.alMaximo(this.cantidadContado);
  }

  /**
   * Mensaje genérico a propósito: nunca debe revelar la cantidad real en
   * stock. Revelar el número solo tiene sentido como aviso de urgencia
   * ("Solo quedan X"), configurable por el admin; acá es solo el límite
   * de lo que se puede pedir, y no hay ningún motivo de negocio para
   * mostrar el dato exacto.
   */
  get mensajeMaximo(): string {
    return 'No hay suficiente stock para la cantidad solicitada';
  }

  get puedeComprar(): boolean {
    return (
      this.tieneOpcionDeCompra &&
      !this.sinStock &&
      !this.stockInsuficienteParaMinimo &&
      !this.agregando
    );
  }

  /** Tramo que corresponde a una cantidad: mayorista si la alcanza. */
  private tramoPara(cantidad: number): TramoPrecio | null {
    const mayorista = this.compra?.mayorista ?? null;
    const minorista = this.compra?.minorista ?? null;

    if (mayorista && cantidad >= mayorista.cantidad_minima) {
      return mayorista;
    }

    return minorista ?? mayorista;
  }

  // --- Contado ---

  get tramoContado(): TramoPrecio | null {
    return this.tramoPara(this.cantidadContado);
  }

  get precioUnitarioContado(): number {
    return this.tramoContado?.precio_unitario ?? 0;
  }

  /**
   * El descuento de campaña aplica al precio `precio_final` de la vitrina.
   * Se muestra (precio anterior tachado + %) solo cuando el precio del tramo
   * vigente ES ese precio: no en el tramo mayorista de un producto que
   * también se vende al por menor, que usa el precio de Gestión de Precios
   * sin descuento.
   */
  get descuentoVigenteContado(): boolean {
    return (
      !!this.producto?.descuento_pct &&
      this.precioUnitarioContado === this.producto.precio_final
    );
  }

  /** Solo multiplica el precio unitario del tramo por las unidades. */
  get totalContado(): number {
    return this.precioUnitarioContado * this.cantidadContado;
  }

  // --- Cuotas ---

  /** La sección "Comprá en Cuotas" existe si el producto tiene cuotas en
   *  algún tramo (estable: no parpadea al cruzar el mínimo mayorista). */
  get tieneCuotas(): boolean {
    return (
      (this.compra?.minorista?.cuotas.length ?? 0) > 0 ||
      (this.compra?.mayorista?.cuotas.length ?? 0) > 0
    );
  }

  get tramoCuotas(): TramoPrecio | null {
    return this.tramoPara(this.cantidadCuotas);
  }

  /**
   * Opciones del selector: la cuota mostrada es la de la CANTIDAD elegida.
   *
   * No se recalcula el recargo: se parte del precio financiado unitario que
   * entrega el backend (`total`) y solo se multiplica por las unidades.
   * Se multiplica ANTES de dividir en cuotas y se redondea una sola vez;
   * multiplicar la cuota unitaria ya redondeada acumularía el error
   * (10 u. a 6 cuotas: 155.833 correcto vs 155.830).
   */
  get cuotasDisponibles(): CuotaVista[] {
    const unidades = this.cantidadCuotas;

    return (this.tramoCuotas?.cuotas ?? []).map((c) => ({
      ...c,
      monto_cuota_total: Math.round((c.total * unidades) / c.cantidad),
    }));
  }

  /** Opción elegida dentro del tramo vigente (por defecto, la primera). */
  get cuotaElegida(): CuotaVista | null {
    return (
      this.cuotasDisponibles.find((c) => c.cantidad === this.cuotaElegidaN) ??
      this.cuotasDisponibles[0] ??
      null
    );
  }

  seleccionarCuota(valor: string): void {
    this.cuotaElegidaN = Number(valor);
  }

  get puedeSolicitarCredito(): boolean {
    return this.puedeComprar && this.cuotaElegida !== null;
  }

  // --- Mensaje de precio mayorista ---

  /** null cuando el producto solo tiene precio minorista. */
  get mensajeMayorista(): string | null {
    const mayorista = this.compra?.mayorista;

    if (!mayorista) {
      return null;
    }

    return this.compra?.minorista
      ? `Se aplica precio mayorista a partir de ${mayorista.cantidad_minima} unidades`
      : `Este producto se vende únicamente a partir de ${mayorista.cantidad_minima} unidades`;
  }

  trackByCuota(_indice: number, cuota: CuotaVista): number {
    return cuota.cantidad;
  }

  /** 5 posiciones, true = estrella llena, según producto.rating redondeado. */
  get estrellas(): boolean[] {
    const llenas = Math.round(this.producto?.rating ?? 0);
    return Array.from({ length: 5 }, (_, i) => i < llenas);
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

  /**
   * Compra al contado (o "Agregar al Carrito" si el producto no tiene
   * cuotas: es la misma acción). Prepara la línea y la agrega al carrito
   * con modalidad = contado. NO se manda el precio: lo recalcula
   * CarritoController::precioPara() y aceptarlo del navegador permitiría
   * manipular el importe a cobrar.
   */
  comprarContado(): void {
    if (!this.producto || !this.puedeComprar) {
      return;
    }

    const linea = this.prepararLineaContado();

    if (!linea) {
      return;
    }

    this.agregando = true;

    this.carritoService
      .agregar({
        car_idpro: linea.producto_id,
        car_idvarp: null,
        car_cantid: linea.cantidad,
        car_modpag: linea.modalidad,
        car_numcuo: null,
      })
      .subscribe({
        next: () => {
          this.agregando = false;
          this.carritoUi.confirmarAgregado();
        },
        error: (err) => {
          this.agregando = false;
          this.toastr.error(this.mensajeErrorCarrito(err), 'No se pudo agregar');
        },
      });
  }

  /**
  * Agrega la línea en modalidad crédito. Igual que comprarContado():
  * solo viajan producto, cantidad, modalidad y nº de cuotas. El recargo
  * y el monto de cuota los recalcula CarritoController::resolverFinanciacion().
  */
  comprarEnCuotas(): void {
    const cuota = this.cuotaElegida;

    if (!this.producto || !cuota || !this.puedeComprar) {
      return;
    }
      
    this.agregando = true;

    this.carritoService
    .agregar({
      car_idpro: this.producto.prd_id,
      car_idvarp: null,
      car_cantid: this.cantidadCuotas,
      car_modpag: 'credito',
      car_numcuo: cuota.cantidad,
    })
    .subscribe({
      next: () => {
        this.agregando = false;
        this.carritoUi.confirmarAgregado();
      },
      error: (err) => {
        this.agregando = false;
        this.toastr.error(this.mensajeErrorCarrito(err), 'No se pudo agregar');
      },
    });
  }
  

  /**
   * Solicitar Crédito: por ahora SOLO prepara la información y la deja
   * guardada para el flujo siguiente (datos personales, laborales y
   * referenciales, que se implementa después). No agrega nada al carrito.
   */
  solicitarCredito(): void {
    if (!this.puedeSolicitarCredito) {
      return;
    }

    const solicitud = this.prepararSolicitudCredito();

    if (!solicitud) {
      return;
    }

    this.solicitudCredito.preparar(solicitud);

    this.toastr.success(
      `${solicitud.cantidad} u. en ${solicitud.numero_cuotas} cuotas`,
      'Solicitud de crédito preparada'
    );
  }

  private prepararLineaContado(): LineaContado | null {
    if (!this.producto || !this.tramoContado) {
      return null;
    }

    return {
      producto_id: this.producto.prd_id,
      cantidad: this.cantidadContado,
      modalidad: 'contado',
      precio_unitario: this.tramoContado.precio_unitario,
    };
  }

  private prepararSolicitudCredito(): SolicitudCredito | null {
    const cuota = this.cuotaElegida;

    if (!this.producto || !cuota) {
      return null;
    }

    return {
      producto_id: this.producto.prd_id,
      cantidad: this.cantidadCuotas,
      modalidad: 'credito',
      numero_cuotas: cuota.cantidad,
      porcentaje_recargo: cuota.recargo_pct,
      precio_unitario_financiado: cuota.total,
      monto_cuota: cuota.monto_cuota,
      monto_cuota_total: cuota.monto_cuota_total,
    };
  }

  private mensajeErrorCarrito(err: any): string {
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
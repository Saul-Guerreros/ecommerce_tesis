import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { CarritoItem, ModalidadPago } from '../../core/models/carrito.model';
import { CarritoService } from '../../core/services/carrito.service';
import { AuthService } from '../auth/service/auth.service';
import { URL_BACKEND } from '../../config/config';

@Component({
  selector: 'app-carrito',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule, FormsModule],
  templateUrl: './carrito.component.html',
  styleUrl: './carrito.component.css',
})
export class CarritoComponent implements OnInit {

  // ---------- Estado del carrito, YA separado por modalidad ----------
  // El backend nunca mezcla contado y crédito en una misma fila (car_modpag
  // forma parte de la identidad de la línea); acá se respeta lo mismo:
  // dos arreglos, dos resúmenes, jamás una suma cruzada (puntos 12 y 13).
  itemsContado: CarritoItem[] = [];
  itemsCredito: CarritoItem[] = [];

  subtotalContado = 0;
  subtotalCredito = 0;

  /**
   * IVA CONTENIDO en el subtotal, no una fila que se sume. Los precios
   * del catálogo (prd_pvtmin/prd_pvtmay) ya salen con el IVA incluido
   * desde PriceService::precioFinal(); "Impuestos" acá es puro desglose
   * informativo del subtotal, no un cargo adicional (Ley 6380/2019).
   */
  impuestosContado = 0;
  impuestosCredito = 0;

  /**
   * Total = Subtotal. El IVA ya está adentro, así que no se suma de
   * nuevo (sumarlo sería cobrarlo dos veces). Queda como getter -no como
   * alias directo de subtotalContado- para que el día que se implemente
   * el código de descuento (punto 17) solo haya que restarlo acá.
   */
  get totalContado(): number {
    return this.subtotalContado;
  }

  get totalCredito(): number {
    return this.subtotalCredito;
  }

  get carritoVacio(): boolean {
    return this.itemsContado.length === 0 && this.itemsCredito.length === 0;
  }

  cargando = true;

  /** Códigos de descuento: campo inerte, punto 17. */
  codigoDescuentoContado = '';
  codigoDescuentoCredito = '';

  /**
   * Filas con una petición en curso: deshabilita sus controles.
   * Se indexa por claveItem() y no por car_id, porque el carrito de
   * invitado no está persistido y todas sus filas tienen car_id en null.
   */
  procesando = new Set<string>();

  private destroyRef = inject(DestroyRef);

  constructor(
    private carritoService: CarritoService,
    private authService: AuthService,
    private router: Router,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    // Suscripción al estado central (patrón Observer). Se da de baja sola
    // al destruirse el componente gracias a takeUntilDestroyed.
    this.carritoService.carrito$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => this.separarPorModalidad(items),
        error: (err) => {
          console.error('Error en el estado del carrito:', err);
          this.separarPorModalidad([]);
        },
      });

    // cargar() ya resuelve internamente si hay sesión (BD) o es invitado
    // (localStorage + previsualización). El resultado entra al Subject vía
    // tap(), así que la suscripción de arriba es la que pinta la vista.
    this.carritoService.cargar().subscribe({
      next: () => (this.cargando = false),
      error: (err) => {
        this.cargando = false;
        this.toastr.error(this.mensajeError(err), 'No se pudo cargar el carrito');
      },
    });
  }

  // ---------- Separación por modalidad (punto 1 y 4) ----------

  private separarPorModalidad(items: CarritoItem[]): void {
    this.itemsContado = items.filter((i) => (i.car_modpag ?? 'contado') === 'contado');
    this.itemsCredito = items.filter((i) => i.car_modpag === 'credito');

    this.subtotalContado = this.itemsContado.reduce(
      (suma, i) => suma + Number(i.car_total), 0
    );
    this.subtotalCredito = this.itemsCredito.reduce(
      (suma, i) => suma + Number(i.car_total), 0
    );

    // Suma de lo que YA viene calculado por línea (Producto::ivaIncluidoEn
    // en el backend, un producto exento aporta 0). Nunca "subtotal / 11":
    // eso solo daría el número correcto si TODO el carrito fuera al 10%,
    // y el sistema tiene 3 tasas por producto (0%, 5%, 10%).
    this.impuestosContado = this.itemsContado.reduce(
      (suma, i) => suma + Number(i.car_iva ?? 0), 0
    );
    this.impuestosCredito = this.itemsCredito.reduce(
      (suma, i) => suma + Number(i.car_iva ?? 0), 0
    );
  }

  // ---------- Cantidad (puntos 5, 6, 7, 8, 9) ----------

  /**
   * Piso real del selector. Para un producto exclusivamente mayorista no
   * es 1: es el umbral configurado (cantidad_minima), o si el dato todavía
   * no llegó, 1 como resguardo (el backend igual lo va a rechazar con 422
   * si corresponde).
   */
  minimoPermitido(item: CarritoItem): number {
    return item.solo_mayorista ? Number(item.cantidad_minima ?? 1) : 1;
  }

  /** Techo real del selector: sin tope si el producto no controla stock. */
  stockMaximo(item: CarritoItem): number {
    if (item.controla_stock === false) {
      return Number.MAX_SAFE_INTEGER;
    }
    if (item.stock_disponible === null || item.stock_disponible === undefined) {
      return Number.MAX_SAFE_INTEGER;
    }
    return Math.max(1, Math.floor(Number(item.stock_disponible)));
  }

  enMinimo(item: CarritoItem): boolean {
    return Number(item.car_cantid) <= this.minimoPermitido(item);
  }

  enMaximo(item: CarritoItem): boolean {
    return Number(item.car_cantid) >= this.stockMaximo(item);
  }

  /**
   * Punto 10: una línea puede haber quedado inválida porque el stock bajó
   * después de agregada. Se detecta al pintar (no se corrige sola, para no
   * mutar en silencio lo que el usuario armó) y se avisa en la fila.
   */
  excedeStock(item: CarritoItem): boolean {
    return (
      item.controla_stock !== false &&
      item.stock_disponible != null &&
      Number(item.car_cantid) > Number(item.stock_disponible)
    );
  }

  aumentar(item: CarritoItem): void {
    const maximo = this.stockMaximo(item);
    const nueva = Number(item.car_cantid) + 1;

    if (nueva > maximo) {
      this.toastr.warning(`Solo hay ${maximo} unidad(es) disponible(s).`, 'Stock insuficiente');
      return;
    }
    this.cambiarCantidad(item, nueva);
  }

  disminuir(item: CarritoItem): void {
    const minimo = this.minimoPermitido(item);
    const nueva = Number(item.car_cantid) - 1;

    if (nueva < minimo) {
      this.toastr.warning(
        `Este producto se vende únicamente a partir de ${minimo} unidades.`,
        'Cantidad mínima'
      );
      return;
    }
    this.cambiarCantidad(item, nueva);
  }

  private cambiarCantidad(item: CarritoItem, cantidad: number): void {
    const clave = this.claveItem(item);

    if (this.procesando.has(clave)) {
      return;
    }
    this.procesando.add(clave);

    // Sin optimistic update: se espera la respuesta del backend, que es
    // quien valida stock y recalcula precio, subtotal, total y —si es
    // crédito— el recargo y el monto de cuota (puntos 5, 6 y 9).
    this.carritoService.actualizarCantidad(item, cantidad).subscribe({
      next: () => this.procesando.delete(clave),
      error: (err) => {
        this.procesando.delete(clave);
        this.toastr.error(this.mensajeError(err), 'No se pudo actualizar');
      },
    });
  }

  // ---------- Eliminar (punto 11) ----------

  eliminar(item: CarritoItem): void {
    const clave = this.claveItem(item);

    if (this.procesando.has(clave)) {
      return;
    }
    this.procesando.add(clave);

    // El servicio elimina por car_id (o por la identidad completa en modo
    // invitado), así que borrar la fila de crédito de un producto nunca
    // toca su fila al contado, aunque sea el mismo producto.
    this.carritoService.eliminar(item).subscribe({
      next: (resp) => {
        this.procesando.delete(clave);
        this.toastr.info(resp.message, this.titulo(item));
      },
      error: (err) => {
        this.procesando.delete(clave);
        this.toastr.error(this.mensajeError(err), 'No se pudo eliminar');
      },
    });
  }

  vaciar(): void {
    if (this.carritoVacio) {
      return;
    }

    // El backend vacía todo el carrito del usuario, ambas modalidades: no
    // existe (ni pide la instrucción) un vaciado parcial por sección.
    this.carritoService.vaciar().subscribe({
      next: (resp) => this.toastr.info(resp.message),
      error: (err) => this.toastr.error(this.mensajeError(err), 'No se pudo vaciar'),
    });
  }

  // ---------- Código de descuento (punto 17: solo estructura visual) ----------

  aplicarDescuento(_modalidad: ModalidadPago): void {
    this.toastr.info(
      'Los códigos de descuento y cupones se habilitarán en una próxima etapa.',
      'Próximamente'
    );
  }

  // ---------- Botones "Finalizar Compra" / "Solicitar Crédito" (14 y 15) ----------
  // Ninguno de los dos implementa el flujo posterior todavía: solo exigen
  // sesión (igual que el carrito viejo) y navegan a la ruta que va a alojar
  // esa funcionalidad en una etapa futura.

  finalizarCompra(): void {
    this.irA('/proceso-de-pago');
  }

  solicitarCredito(): void {
    this.irA('/solicitar-credito');
  }

  private irA(ruta: string): void {
    if (!this.authService.isLoggedIn()) {
      this.toastr.info('Iniciá sesión para continuar.', 'Sesión requerida');
      this.router.navigate(['/login'], { queryParams: { redirect: ruta } });
      return;
    }
    this.router.navigate([ruta]);
  }

  // ---------- Helpers de vista ----------

  /**
   * Identidad estable de cada fila para el @for y el Set de procesando.
   * Incluye modalidad y cuotas: el mismo producto al contado y en 6 cuotas
   * son dos filas y no pueden compartir clave (punto 4).
   */
  claveItem(item: CarritoItem): string {
    if (item.car_id) {
      return `c-${item.car_id}`;
    }
    const modalidad = item.car_modpag ?? 'contado';
    const cuotas = item.car_numcuo ?? 0;
    return `p-${item.car_idpro}-${item.car_idvarp ?? 0}-${modalidad}-${cuotas}`;
  }

  enProceso(item: CarritoItem): boolean {
    return this.procesando.has(this.claveItem(item));
  }

  titulo(item: CarritoItem): string {
    return item.publicacion_web?.pub_titulo ?? item.prd_descri;
  }

  enlace(item: CarritoItem): string | null {
    return item.publicacion_web?.pub_slug
      ? `/producto/${item.publicacion_web.pub_slug}`
      : null;
  }

  /** Total a pagar por mes de esta línea: monto de cada cuota × cantidad. */
  cuotaMensualLinea(item: CarritoItem): number {
    return Number(item.car_montocuo ?? 0) * Number(item.car_cantid);
  }

  /**
   * Completa la URL de la imagen con el dominio del backend. Mismo
   * criterio que en producto-detalle y home: la API devuelve rutas
   * relativas, y el chequeo de 'http' lo vuelve idempotente.
   */
  resolverImagen(ruta: string | null | undefined): string {
    if (!ruta) {
      return 'assets/images/products/placeholder.png';
    }
    if (ruta.startsWith('http')) {
      return ruta;
    }
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
  }

  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primerCampo = Object.keys(errores)[0];
      return errores[primerCampo]?.[0] ?? 'Intentá nuevamente.';
    }
    return err?.error?.message ?? 'Intentá nuevamente.';
  }
}

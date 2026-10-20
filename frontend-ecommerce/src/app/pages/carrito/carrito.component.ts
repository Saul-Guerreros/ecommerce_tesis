import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { ToastrService } from 'ngx-toastr';
import { CarritoItem, ModalidadPago } from '../../core/models/carrito.model';
import { CarritoService } from '../../core/services/carrito.service';
import { AuthService } from '../auth/service/auth.service';
import { URL_BACKEND } from '../../config/config';
import { AuthModalComponent } from '../../shared/components/auth-modal/auth-modal.component';

@Component({
  selector: 'app-carrito',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule, FormsModule],
  templateUrl: './carrito.component.html',
  styleUrl: './carrito.component.css',
})
export class CarritoComponent implements OnInit {

  /**
   * Signal global de sesion (AuthService.sesion). Se usa en el HTML para
   * ocultar "Iniciar Sesion" / "Comprar como Invitado" / "Credito sin
   * Registrarse" en AMBAS tarjetas apenas hay sesion activa, sin importar
   * desde cual de las dos se inicio sesion (la sesion no es por tarjeta).
   */
  get sesionActiva(): boolean {
    return this.authService.sesion();
  }

  /**
   * Pulso visual de 2.5s sobre "Finalizar Compra" y "Solicitar Credito"
   * apenas se cierra el modal con un login/registro exitoso.
   */
  destacarResumen = false;

  /**
   * Aviso de validacion (texto rojo) bajo los botones "Iniciar Sesion" /
   * "Comprar como Invitado" de cada tarjeta. Se prende cuando el usuario
   * intenta "Finalizar Compra" / "Solicitar Credito" sin sesion: NO abre
   * el modal por su cuenta (eso es trabajo exclusivo del boton "Iniciar
   * Sesion"), solo señala que hace falta resolver el acceso primero.
   * Vive dentro del mismo *ngIf="!sesionActiva" que esos dos botones, asi
   * que desaparece solo en cuanto hay sesion.
   */
  avisoAccesoContado = false;
  avisoAccesoCredito = false;

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

  // ---------- Resumen "Compra a Crédito" (esquema de 6 líneas) ----------
  // Precio al contado / Recargo por financiación / TOTAL A PAGAR /
  // Impuestos / Plazo / Cuota mensual. subtotalCredito y totalCredito ya
  // existen arriba (punto 13 original); esto agrega lo que faltaba sin
  // tocar nada de lo ya implementado.

  /** Suma de lo que cada línea costaría SIN financiar (car_precio, no car_total). */
  get precioContadoCredito(): number {
    return this.itemsCredito.reduce(
      (suma, i) => suma + Number(i.car_precio) * Number(i.car_cantid), 0
    );
  }

  /** Lo que el cliente paga de más por financiar: TOTAL - contado. */
  get recargoFinanciacionCredito(): number {
    return this.subtotalCredito - this.precioContadoCredito;
  }

  /**
   * Plazo en meses: el plan MÁS LARGO del carrito a crédito. Con un
   * único producto es simplemente su cfc_cantidad elegida; con varios
   * productos en planes distintos, el que llega más lejos en el tiempo.
   */
  get plazoCredito(): number {
    if (this.itemsCredito.length === 0) return 0;
    return Math.max(...this.itemsCredito.map((i) => Number(i.car_numcuo ?? 0)));
  }

  /**
   * Cuota mensual del PRIMER TRAMO: suma de la cuota de cada línea
   * (car_montocuo × cantidad). Con planes mixtos (ej. 6x y 12x) esto NO
   * es una cuota constante durante todo el plazo -- del mes 7 al 12 la
   * cuota real baja porque el producto de 6 cuotas ya se pagó. Por eso
   * el HTML cambia la etiqueta cuando hayPlanesMixtos.
   */
  get cuotaMensualCredito(): number {
    return this.itemsCredito.reduce(
      (suma, i) => suma + Number(i.car_montocuo ?? 0) * Number(i.car_cantid), 0
    );
  }

  /** true si el carrito a crédito mezcla más de un plan de cuotas. */
  get hayPlanesMixtos(): boolean {
    const planes = new Set(this.itemsCredito.map((i) => i.car_numcuo ?? 0));
    return planes.size > 1;
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
    private toastr: ToastrService,
    private dialog: MatDialog,
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

  // ---------- Botones bajo cada tarjeta ----------

  /**
   * "Vaciar Carrito Contado/Credito": borra solo esa seccion, la otra
   * modalidad queda intacta (a diferencia de vaciar(), que borra todo).
   */
  vaciarModalidad(modalidad: ModalidadPago): void {
    const lista = modalidad === 'contado' ? this.itemsContado : this.itemsCredito;
    if (lista.length === 0) {
      return;
    }

    this.carritoService.vaciarModalidad(modalidad).subscribe({
      next: (resp) => this.toastr.info(resp.message),
      error: (err) => this.toastr.error(this.mensajeError(err), 'No se pudo vaciar'),
    });
  }

  /**
   * Abre el login/registro en un modal (Angular Material Dialog) sobre el
   * carrito: no se navega a otra pagina y los productos no se tocan.
   * Al cerrarse con 'login' (autenticacion exitosa), dispara el pulso
   * sobre "Finalizar Compra" / "Solicitar Credito".
   */
  abrirModalLogin(): void {
    // El usuario ya está resolviendo el acceso: el aviso de validación
    // deja de tener sentido mientras el modal está abierto.
    this.avisoAccesoContado = false;
    this.avisoAccesoCredito = false;

    const ref = this.dialog.open(AuthModalComponent, {
      width: '520px',
      maxWidth: '95vw',
      autoFocus: false,
      panelClass: 'auth-modal-panel',
    });

    ref.afterClosed().subscribe((resultado) => {
      if (resultado === 'login') {
        this.destacarResumen = true;
        setTimeout(() => (this.destacarResumen = false), 2500);
      }
    });
  }

  /**
   * Solo diseño por ahora: el modulo de checkout de invitado va aparte.
   * Cuando se implemente, ese flujo (datos de invitado -> resumen) debe
   * terminar igual en finalizarCompra() -- no es un camino paralelo que
   * se salta el botón "Finalizar Compra", solo evita exigir sesión antes
   * de llegar ahí. Por eso ya limpia el aviso, igual que abrirModalLogin().
   */
  comprarComoInvitado(): void {
    this.avisoAccesoContado = false;
    this.toastr.info('Esta funcionalidad se habilitará en una próxima etapa.', 'Próximamente');
  }

  /** Solo diseño por ahora: mismo caso que comprarComoInvitado(), para Crédito. */
  creditoSinRegistrarse(): void {
    this.avisoAccesoCredito = false;
    this.toastr.info('Esta funcionalidad se habilitará en una próxima etapa.', 'Próximamente');
  }

  // ---------- Código de descuento (punto 17: solo estructura visual) ----------

  aplicarDescuento(_modalidad: ModalidadPago): void {
    this.toastr.info(
      'Los códigos de descuento y cupones se habilitarán en una próxima etapa.',
      'Próximamente'
    );
  }

  // ---------- Botones "Finalizar Compra" / "Solicitar Crédito" (14 y 15) ----------
  // Ninguno de los dos implementa el flujo posterior todavía: solo navegan
  // a la ruta que va a alojar esa funcionalidad en una etapa futura.
  //
  // Siguen exigiendo sesión, pero YA NO abren el modal por su cuenta: eso
  // le pisaba el rol al botón "Iniciar Sesión" (quedaba sin usar, porque
  // el modal ya se habría abierto solo) y además, el día de mañana
  // "Comprar como Invitado" también necesita desembocar acá sin que esto
  // le exija sesión de entrada. Entonces el único trabajo de este método,
  // si no hay sesión, es prender el aviso de validación bajo los botones
  // de la tarjeta -- quien decide CÓMO resolver el acceso es el usuario,
  // tocando "Iniciar Sesión" o "Comprar como Invitado".

  finalizarCompra(): void {
    if (!this.sesionActiva) {
      this.avisoAccesoContado = true;
      return;
    }
    this.router.navigate(['/proceso-de-pago']);
  }

  solicitarCredito(): void {
    if (!this.sesionActiva) {
      this.avisoAccesoCredito = true;
      return;
    }
    this.router.navigate(['/solicitar-credito']);
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
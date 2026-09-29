import { CommonModule } from '@angular/common';
import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';

import { URL_BACKEND } from '../../../config/config';
import { CarritoItem } from '../../../core/models/carrito.model';
import { CarritoService } from '../../../core/services/carrito.service';
import { CarritoUiService } from '../../../core/services/carrito-ui.service';

@Component({
  selector: 'app-carrito-offcanvas',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './carrito-offcanvas.component.html',
  styleUrl: './carrito-offcanvas.component.css',
})
export class CarritoOffcanvasComponent {

  // ---------- Estado leído del CarritoService (patrón Observer) ----------
  items: CarritoItem[] = [];
  subtotal = 0;
  unidades = 0;

  /**
   * Cantidad "en pantalla" de cada fila, indexada por claveItem().
   *
   * Es estado LOCAL del componente y a propósito NO vive en el
   * CarritoService: representa una edición todavía no confirmada. El
   * service solo conoce lo guardado. Esta separación es la que permite
   * que el botón Actualizar exista (punto 7): el botón se muestra
   * cuando estos dos valores difieren.
   */
  cantidades = new Map<string, number>();

  /** Filas con una petición en curso: evita el doble envío. */
  procesando = new Set<string>();

  private destroyRef = inject(DestroyRef);

  constructor(
    private carritoService: CarritoService,
    private carritoUi: CarritoUiService,
    private toastr: ToastrService
  ) {
    this.carritoService.carrito$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => this.sincronizarEstado(items),
        error: (err) => {
          console.error('Error en el estado del carrito:', err);
          this.sincronizarEstado([]);
        },
      });
  }

  // ---------------------------------------------------------------
  // Sincronización con el estado central
  // ---------------------------------------------------------------

  /**
   * El mapa de cantidades se actualiza de forma INCREMENTAL (alta de filas
   * nuevas, baja de filas borradas) en vez de reconstruirse entero.
   *
   * Si se reconstruyera, cualquier emisión del BehaviorSubject -- por
   * ejemplo, actualizar el Producto A -- pisaría la cantidad que el
   * usuario ya escribió en el Producto C y todavía no confirmó. El punto 9
   * exige justamente lo contrario: cada fila se edita y se guarda de forma
   * independiente.
   */
  private sincronizarEstado(items: CarritoItem[]): void {
    this.items = items;
    this.subtotal = items.reduce((suma, i) => suma + Number(i.car_total), 0);
    this.unidades = items.reduce((suma, i) => suma + Number(i.car_cantid), 0);

    const clavesVigentes = new Set(items.map((i) => this.claveItem(i)));

    for (const item of items) {
      const clave = this.claveItem(item);
      if (!this.cantidades.has(clave)) {
        this.cantidades.set(clave, Number(item.car_cantid));
      }
    }

    for (const clave of [...this.cantidades.keys()]) {
      if (!clavesVigentes.has(clave)) {
        this.cantidades.delete(clave);
        this.procesando.delete(clave);
      }
    }
  }

  // ---------------------------------------------------------------
  // Control de cantidad (puntos 6 y 7)
  // ---------------------------------------------------------------

  cantidadEnPantalla(item: CarritoItem): number {
    return this.cantidades.get(this.claveItem(item)) ?? Number(item.car_cantid);
  }

  /** El botón Actualizar aparece SOLO si hay una diferencia sin confirmar. */
  hayCambioPendiente(item: CarritoItem): boolean {
    return this.cantidadEnPantalla(item) !== Number(item.car_cantid);
  }

  disminuir(item: CarritoItem): void {
    const actual = this.cantidadEnPantalla(item);
    if (actual > 1) {
      this.cantidades.set(this.claveItem(item), actual - 1);
    }
  }

  aumentar(item: CarritoItem): void {
    const actual = this.cantidadEnPantalla(item);
    const maximo = this.stockMaximo(item);

    if (actual + 1 > maximo) {
      this.avisarStock(maximo);
      return;
    }

    this.cantidades.set(this.claveItem(item), actual + 1);
  }

  /**
   * Entrada manual (punto 6). Se normaliza acá y no en el (click) del
   * botón porque el usuario puede escribir "0", "-3", vacío o decimales.
   */
  cambiarCantidad(item: CarritoItem, valor: number | string | null): void {
    const numero = Math.floor(Number(valor));

    if (!Number.isFinite(numero) || numero < 1) {
      this.cantidades.set(this.claveItem(item), 1);
      return;
    }

    const maximo = this.stockMaximo(item);

    if (numero > maximo) {
      this.avisarStock(maximo);
      this.cantidades.set(this.claveItem(item), maximo);
      return;
    }

    this.cantidades.set(this.claveItem(item), numero);
  }

  puedeAumentar(item: CarritoItem): boolean {
    return this.cantidadEnPantalla(item) < this.stockMaximo(item);
  }

  // ---------------------------------------------------------------
  // Stock (punto 11)
  // ---------------------------------------------------------------

  /**
   * Tope del control de cantidad.
   *
   * controla_stock === false  -> el producto no lleva inventario: sin tope.
   * stock_disponible ausente  -> dato desconocido: sin tope en el cliente.
   *                              El backend igual valida y devuelve 422.
   *
   * En ningún caso esto reemplaza la validación del servidor: es UX.
   */
  private stockMaximo(item: CarritoItem): number {
    if (item.controla_stock === false) {
      return Number.MAX_SAFE_INTEGER;
    }

    const disponible = item.stock_disponible;

    if (disponible === null || disponible === undefined) {
      return Number.MAX_SAFE_INTEGER;
    }

    return Math.max(1, Math.floor(Number(disponible)));
  }

  private avisarStock(maximo: number): void {
    this.toastr.warning(
      `Solo hay ${maximo} unidad(es) disponible(s).`,
      'La cantidad supera el stock disponible'
    );
  }

  // ---------------------------------------------------------------
  // Acciones (puntos 8 y 10)
  // ---------------------------------------------------------------

  /**
   * Confirma el cambio de UNA fila. El recálculo de subtotal y total no se
   * hace acá: lo devuelve el backend (CarritoController::update ya lo
   * resuelve con el precio publicado) y entra al estado por el tap() del
   * CarritoService. Duplicar el cálculo en el cliente permitiría que la
   * pantalla muestre un total que no es el que se va a cobrar.
   */
  actualizar(item: CarritoItem): void {
    const clave = this.claveItem(item);
    const nuevaCantidad = this.cantidadEnPantalla(item);

    if (this.procesando.has(clave) || !this.hayCambioPendiente(item)) {
      return;
    }

    if (nuevaCantidad < 1) {
      this.toastr.error('La cantidad mínima es 1.', 'Cantidad inválida');
      return;
    }

    this.procesando.add(clave);

    this.carritoService.actualizarCantidad(item, nuevaCantidad).subscribe({
      next: (resp) => {
        this.procesando.delete(clave);
        // Se alinea la cantidad en pantalla con la guardada: el botón
        // Actualizar desaparece solo porque ya no hay diferencia.
        this.cantidades.set(this.claveItem(resp.data), Number(resp.data.car_cantid));
        this.toastr.success(resp.message, this.tituloItem(item));
      },
      error: (err) => {
        this.procesando.delete(clave);
        // Se revierte a lo guardado para no dejar en pantalla un número
        // que el servidor rechazó.
        this.cantidades.set(clave, Number(item.car_cantid));
        this.toastr.error(this.mensajeError(err), 'No se pudo actualizar');
      },
    });
  }

  eliminar(item: CarritoItem): void {
    const clave = this.claveItem(item);

    if (this.procesando.has(clave)) {
      return;
    }

    this.procesando.add(clave);

    this.carritoService.eliminar(item).subscribe({
      next: (resp) => {
        this.procesando.delete(clave);
        this.toastr.info(this.tituloItem(item), resp.message);
      },
      error: (err) => {
        this.procesando.delete(clave);
        this.toastr.error(this.mensajeError(err), 'No se pudo eliminar');
      },
    });
  }

  enProceso(item: CarritoItem): boolean {
    return this.procesando.has(this.claveItem(item));
  }

  /** Puntos 13 y 14: al navegar, el drawer se cierra. */
  cerrar(): void {
    this.carritoUi.cerrar();
  }

  // ---------------------------------------------------------------
  // Helpers de vista
  // ---------------------------------------------------------------

  /**
   * Identidad estable de cada fila. No alcanza con car_id: el carrito de
   * invitado se arma con instancias no persistidas y car_id viene null en
   * todas, lo que daría claves duplicadas (NG0955) en el @for.
   */
  claveItem(item: CarritoItem): string {
    if (item.car_id) {
      return `c-${item.car_id}`;
    }

    // La modalidad y el nº de cuotas forman parte de la identidad: el mismo
    // producto al contado y en cuotas son dos filas distintas y no pueden
    // compartir clave, o comparten también la cantidad en pantalla.
    const modalidad = item.car_modpag ?? 'contado';
    const cuotas = item.car_numcuo ?? 0;

    return `p-${item.car_idpro}-${item.car_idvarp ?? 0}-${modalidad}-${cuotas}`;
  }

  /** True si la fila se agregó en modalidad crédito (con plan de cuotas). */
  esCredito(item: CarritoItem): boolean {
    return item.car_modpag === 'credito';
  }

  // ---------------------------------------------------------------
  // Separación por modalidad (solo para agrupar la vista en dos
  // secciones, igual que en la página completa del carrito). No
  // reemplaza a `items`: cada <article> sigue siendo la misma fila,
  // con los mismos datos y las mismas acciones; esto solo decide en
  // qué bloque se pinta.
  // ---------------------------------------------------------------

  get itemsContado(): CarritoItem[] {
    return this.items.filter((i) => !this.esCredito(i));
  }

  get itemsCredito(): CarritoItem[] {
    return this.items.filter((i) => this.esCredito(i));
  }

  get unidadesContado(): number {
    return this.itemsContado.reduce((suma, i) => suma + Number(i.car_cantid), 0);
  }

  get unidadesCredito(): number {
    return this.itemsCredito.reduce((suma, i) => suma + Number(i.car_cantid), 0);
  }

  get subtotalContado(): number {
    return this.itemsContado.reduce((suma, i) => suma + Number(i.car_total), 0);
  }

  get subtotalCredito(): number {
    return this.itemsCredito.reduce((suma, i) => suma + Number(i.car_total), 0);
  }

  /** Total a pagar por mes: suma de (monto de cuota × cantidad) de cada línea a crédito. */
  get cuotaMensualTotalCredito(): number {
    return this.itemsCredito.reduce(
      (suma, i) => suma + Number(i.car_montocuo ?? 0) * Number(i.car_cantid), 0
    );
  }

  tituloItem(item: CarritoItem): string {
    return item.publicacion_web?.pub_titulo ?? item.prd_descri;
  }

  enlaceItem(item: CarritoItem): string | null {
    return item.publicacion_web?.pub_slug
      ? `/producto/${item.publicacion_web.pub_slug}`
      : null;
  }

  resolverImagen(ruta: string | null | undefined): string {
    if (!ruta) {
      return 'assets/images/products/placeholder.png';
    }
    if (ruta.startsWith('http')) {
      return ruta;
    }
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
  }

  /** Primer mensaje del 422 de Laravel: { errors: { campo: ["..."] } } */
  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primerCampo = Object.keys(errores)[0];
      return errores[primerCampo]?.[0] ?? 'Intentá nuevamente.';
    }
    return err?.error?.message ?? 'Intentá nuevamente.';
  }
}
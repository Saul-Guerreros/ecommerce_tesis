import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { CarritoItem } from '../../core/models/carrito.model';
import { CarritoService } from '../../core/services/carrito.service';
import { AuthService } from '../auth/service/auth.service';
import { URL_BACKEND } from '../../config/config';
@Component({
  selector: 'app-carrito',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule],
  templateUrl: './carrito.component.html',
  styleUrl: './carrito.component.css',   // ← nueva
})

export class CarritoComponent implements OnInit {

  // ---------- Estado del carrito (patrón Observer) ----------
  // Propiedades simples alimentadas por la suscripción al BehaviorSubject
  // del service. Ya no son signal() ni computed().
  items: CarritoItem[] = [];
  total: number = 0;

  cargando = true;

  /**
   * Filas con una petición en curso: deshabilita sus botones.
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
    // Suscripción al estado central. Se da de baja sola al destruirse el
    // componente: el curso nunca se desuscribe y deja la suscripción viva.
    this.carritoService.carrito$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.items = items;
          this.total = items.reduce(
            (suma, item) => suma + Number(item.car_total),
            0
          );
        },
        error: (err) => {
          console.error('Error en el estado del carrito:', err);
          this.items = [];
          this.total = 0;
        },
      });

      



    // cargar() ya resuelve internamente si hay sesión (BD) o es invitado
    // (localStorage + previsualización): no hace falta exigir login acá.
    // El resultado entra al Subject vía tap(), así que la suscripción de
    // arriba es la que pinta la vista.
    this.carritoService.cargar().subscribe({
      next: () => (this.cargando = false),
      error: (err) => {
        this.cargando = false;
        this.toastr.error(this.mensajeError(err), 'No se pudo cargar el carrito');
      },
    });
  }

  // ---------- Cantidad ----------

  aumentar(item: CarritoItem): void {
    this.cambiarCantidad(item, Number(item.car_cantid) + 1);
  }

  disminuir(item: CarritoItem): void {
    const nueva = Number(item.car_cantid) - 1;
    if (nueva < 1) {
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

    // Sin optimistic update: esperamos la respuesta del backend, que es
    // quien valida el stock y recalcula el total. Así no hay que revertir
    // nada si la validación falla.
    this.carritoService.actualizarCantidad(item, cantidad).subscribe({
      next: () => this.procesando.delete(clave),
      error: (err) => {
        this.procesando.delete(clave);
        this.toastr.error(this.mensajeError(err), 'No se pudo actualizar');
      },
    });
  }

  // ---------- Eliminar ----------

  eliminar(item: CarritoItem): void {
    const clave = this.claveItem(item);

    if (this.procesando.has(clave)) {
      return;
    }
    this.procesando.add(clave);

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
    if (this.items.length === 0) {
      return;
    }

    this.carritoService.vaciar().subscribe({
      next: (resp) => this.toastr.info(resp.message),
      error: (err) => this.toastr.error(this.mensajeError(err), 'No se pudo vaciar'),
    });
  }

  // ---------- Checkout ----------

  /**
   * Único punto donde se exige sesión: el carrito en sí es visible y
   * editable para invitados, pero para pagar sí necesitamos una cuenta.
   */
  finalizarCompra(): void {
    if (!this.authService.isLoggedIn()) {
      this.toastr.info('Iniciá sesión para continuar con el pago.', 'Sesión requerida');
      this.router.navigate(['/login'], {
        queryParams: { redirect: '/proceso-de-pago' },
      });
      return;
    }

    this.router.navigate(['/proceso-de-pago']);
  }

  // ---------- Helpers de vista ----------

  /**
   * Identidad estable de cada fila, usada por el @for y por el Set de
   * procesando. Misma implementación que en HeaderComponent.
   */
  claveItem(item: CarritoItem): string {
    return item.car_id
      ? `c-${item.car_id}`
      : `p-${item.car_idpro}-${item.car_idvarp ?? 0}`;
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

    /**
   * Completa la URL de la imagen con el dominio del backend.
   * Mismo criterio que resolverImagen() en producto-detalle y
   * imagenVitrina() en home: la API devuelve rutas relativas.
   * El chequeo de 'http' lo vuelve idempotente, por si algún
   * endpoint ya manda la URL absoluta.
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
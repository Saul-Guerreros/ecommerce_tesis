import { CommonModule } from '@angular/common';
import { Component, DestroyRef, afterNextRender, afterRender, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { ToastrService } from 'ngx-toastr';
import { CarritoItem } from '../../core/models/carrito.model';
import { CarritoService } from '../../core/services/carrito.service';
import { AuthService } from '../../pages/auth/service/auth.service';
import { HomeService } from '../../pages/home/service/home.service';
import { URL_BACKEND } from '../../config/config';
@Component({
  selector: 'app-header',
  standalone: true,
  imports: [FormsModule, RouterModule, CommonModule],
  templateUrl: './header.component.html',
  styleUrl: './header.component.css',
})
export class HeaderComponent {

  categories_menus: any = [];
  currency: string = 'PYG';

  // TODO: reemplazar por datos reales desde un servicio (ej. homeService.brands())
  brands: any = [
    { id: 1, name: 'Apple', logo: 'assets/images/brands/apple.svg' },
    { id: 2, name: 'Samsung', logo: 'assets/images/brands/samsung.svg' },
    { id: 3, name: 'Sony', logo: 'assets/images/brands/sony.svg' },
    { id: 4, name: 'Microsoft', logo: 'assets/images/brands/microsoft.svg' },
    { id: 5, name: 'Intel', logo: 'assets/images/brands/intel.svg' },
    { id: 6, name: 'HP', logo: 'assets/images/brands/hp.svg' },
    { id: 7, name: 'LG', logo: 'assets/images/brands/lg.svg' },
    { id: 8, name: 'Lenovo', logo: 'assets/images/brands/lenovo.svg' },
    { id: 9, name: 'ASUS', logo: 'assets/images/brands/asus.svg' },
    { id: 10, name: 'Acer', logo: 'assets/images/brands/acer.svg' },
    { id: 11, name: 'Dell', logo: 'assets/images/brands/dell.svg' },
    { id: 12, name: 'Canon', logo: 'assets/images/brands/canon.svg' },
  ];

  isLoading: boolean = false;
  searchT: string = '';

  // ---------- Estado del carrito (patrón Observer) ----------
  // Ya no son signals: son propiedades simples que se refrescan cada vez
  // que CarritoService emite un nuevo valor por su BehaviorSubject.
  listCarts: CarritoItem[] = [];
  totalCarts: number = 0;

  private destroyRef = inject(DestroyRef);

  constructor(
    public homeService: HomeService,
    public cookieService: CookieService,
    public carritoService: CarritoService,
    public authService: AuthService,
    private toastr: ToastrService
  ) {
    // Suscripción al estado central del carrito.
    // Va en el constructor (contexto de inyección) y se da de baja sola con
    // takeUntilDestroyed: el curso nunca se desuscribe del BehaviorSubject,
    // lo que deja la suscripción viva después de destruido el componente.
    this.carritoService.carrito$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.listCarts = items;
          this.totalCarts = items.reduce(
            (suma, item) => suma + Number(item.car_total),
            0
          );
        },
        error: (err) => {
          console.error('Error en el estado del carrito:', err);
          this.listCarts = [];
          this.totalCarts = 0;
        },
      });

    afterNextRender(() => {
      this.homeService.menus().subscribe({
        next: (resp: any) => {
          this.categories_menus = resp.categories_menus;
        },
        error: (err) => {
          console.error('No se pudieron cargar las categorías del menú:', err.message);
          this.categories_menus = [];
        },
      });

      this.currency = this.cookieService.get('currency') || 'PYG';

      // Sin condicionar a isLoggedIn(): cargar() ya decide internamente si
      // trae el carrito de la BD (usuario con sesión) o lo previsualiza
      // desde localStorage (invitado). El resultado entra al Subject vía tap(),
      // así que acá no hace falta asignar nada.
      this.carritoService.cargar().subscribe({
        error: (err) => console.error('No se pudo cargar el carrito:', err),
      });
    });

    afterRender(() => {
      setTimeout(() => {
        this.isLoading = true;
      }, 50);
    });
  }

  /** El usuario se lee de AuthService, no a través del carrito. */
  get user(): any {
    return this.authService.user;
  }

  logout() {
    this.authService.logout();
    setTimeout(() => {
      window.location.reload();
    }, 50);
  }

  // ---------- Acciones sobre el carrito ----------

  eliminarDelCarrito(item: CarritoItem) {
    this.carritoService.eliminar(item).subscribe({
      next: (resp) => {
        // No se toca listCarts acá: el service ya emitió la lista nueva
        // y la suscripción del constructor actualizó la vista.
        this.toastr.info(
          'Se eliminó el producto ' + this.tituloItem(item) + ' del carrito de compra',
          resp.message
        );
      },
      error: (err) => {
        this.toastr.error(this.mensajeError(err), 'No se pudo eliminar');
      },
    });
  }

  // ---------- Helpers de vista ----------

  tituloItem(item: CarritoItem): string {
    return item.publicacion_web?.pub_titulo ?? item.prd_descri;
  }

  enlaceItem(item: CarritoItem): string | null {
    return item.publicacion_web?.pub_slug
      ? `/producto/${item.publicacion_web.pub_slug}`
      : null;
  }

  /**
   * Identidad estable de cada fila para el @for del offcanvas.
   * No se puede usar car_id solo: el carrito de invitado se arma con
   * instancias no persistidas, donde car_id viene en null para todas.
   */
  claveItem(item: CarritoItem): string {
    return item.car_id
      ? `c-${item.car_id}`
      : `p-${item.car_idpro}-${item.car_idvarp ?? 0}`;
  }

  getIconMenu(menu: any) {
    var miDiv: any = document.getElementById('icon-' + menu.id);
    miDiv.innerHTML = menu.icon;
    return '';
  }

  searchProduct() {
    window.location.href = '/productos-busqueda?search=' + this.searchT;
  }

  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primerCampo = Object.keys(errores)[0];
      return errores[primerCampo]?.[0] ?? 'Intentá nuevamente.';
    }
    return err?.error?.message ?? 'Intentá nuevamente.';
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
}
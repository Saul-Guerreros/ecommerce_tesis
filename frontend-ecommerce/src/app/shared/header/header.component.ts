import { CommonModule } from '@angular/common';
import { Component, DestroyRef, afterNextRender, afterRender, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { CarritoService } from '../../core/services/carrito.service';
import { AuthService } from '../../pages/auth/service/auth.service';
import { HomeService } from '../../pages/home/service/home.service';
import { CarritoOffcanvasComponent } from '../components/carrito-offcanvas/carrito-offcanvas.component';
// --- import nuevo ---
import { ConfirmacionCarritoComponent } from '../components/confirmacion-carrito/confirmacion-carrito.component';
import { BuscadorSugerenciasComponent } from '../components/buscador-sugerencias/buscador-sugerencias.component';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [
    FormsModule,
    RouterModule,
    CommonModule,
    CarritoOffcanvasComponent,
    ConfirmacionCarritoComponent,
    BuscadorSugerenciasComponent,
  ],  templateUrl: './header.component.html',
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

  // ---------- Estado del carrito (patrón Observer) ----------
  // El header ya no conoce las filas del carrito ni los totales: lo único
  // que necesita es el número del badge. El detalle de los ítems, el
  // subtotal y todas las acciones (cantidad, actualizar, eliminar) pasaron
  // a CarritoOffcanvasComponent.
  //
  // Se cuentan UNIDADES y no filas: dos unidades de un producto más una de
  // otro son 3, no 2.
  totalUnidades: number = 0;

  private destroyRef = inject(DestroyRef);

  constructor(
    public homeService: HomeService,
    public cookieService: CookieService,
    public carritoService: CarritoService,
    public authService: AuthService,
    private router: Router,
  ) {
    // Suscripción al derivado del estado central del carrito.
    // Va en el constructor (contexto de inyección) y se da de baja sola
    // con takeUntilDestroyed.
    this.carritoService.cantidadUnidades$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (unidades) => (this.totalUnidades = unidades),
        error: (err) => {
          console.error('Error en el estado del carrito:', err);
          this.totalUnidades = 0;
        },
      });

    afterNextRender(() => {
      this.homeService.menus().subscribe({
        next: (resp:any) => {
          //console.log(resp);
          this.categories_menus = resp.data;
        },
        error: (err) => {
          console.error('No se pudieron cargar las categorías del menú:', err.message);
          this.categories_menus = [];
        },
      });

      this.currency = this.cookieService.get('currency') || 'PYG';

      // Sin condicionar a isLoggedIn(): cargar() ya decide internamente si
      // trae el carrito de la BD (usuario con sesión) o lo previsualiza
      // desde localStorage (invitado). El resultado entra al Subject vía
      // tap(), así que acá no hace falta asignar nada: la suscripción de
      // arriba actualiza el badge sola.
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

  /**
   * AuthService.logout() ya hace todo: POST /auth/logout, limpia token +
   * localStorage + el signal `sesion`, y redirige a /login — todo eso
   * ADENTRO del callback de la petición HTTP (es asincrónico).
   *
   * Antes esto se completaba con un window.location.reload() a los 50ms,
   * sin esperar esa respuesta: como el POST casi siempre tarda más que
   * eso, la pagina se recargaba ANTES de que se borrara el localStorage,
   * así que volvía a arrancar con el token todavía guardado -- por eso
   * "no cerraba nada". No hace falta reload de ningún tipo: en cuanto
   * sesionExpirada() limpia authService.user, el *ngIf="user" del header
   * reacciona solo (Angular corre detección de cambios después de la
   * respuesta HTTP).
   */
  logout() {
    // Se queda en la misma página en la que estaba, ya en modo invitado
    // (si esa página es privada, el guard correspondiente la intercepta
    // solo y manda a /login -- ver el comentario en AuthService.logout()).
    this.authService.logout(this.router.url);
  }

  // changeCurrency(val:string){
  //   if(this.user){
  //     this.cartService.deleteCartsAll().subscribe((resp:any) => {
  //       this.cookieService.set("currency",val);
  //       window.location.reload();
  //       //console.log(resp);
  //     })
  //   }else{
  //     this.cookieService.set("currency",val);
  //     setTimeout(() => {
  //       window.location.reload();
  //     }, 25);
  //   }
  // }

  // La búsqueda del header ahora vive en BuscadorSugerenciasComponent
  // (resultados mientras se escribe + navegación sin recargar la página).
}
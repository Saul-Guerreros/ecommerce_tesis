import { Component, ElementRef, NgZone, OnInit, ViewChild, afterNextRender } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CarritoService } from '../../../core/services/carrito.service';
import { GoogleIdentityService } from '../../../core/services/google-identity.service';
import { environment } from '../../../../environments/environment';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule, RouterModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.css',
})
export class LoginComponent implements OnInit {
  email = '';
  password = '';
  /** true: sesión de 30 días. false: duración normal (JWT_TTL). */
  remember = false;
  code_user = '';

  /** Ruta a la que volver tras iniciar sesión (ej. /proceso-de-pago). */
  redirect = '/';

  /** Bloquea el botón mientras dura login + sincronización del carrito. */
  procesando = false;

  /** Contenedor donde Google dibuja su botón oficial. */
  @ViewChild('googleBtn') googleBtn?: ElementRef<HTMLDivElement>;

  constructor(
    private toastr: ToastrService,
    private authService: AuthService,
    private carritoService: CarritoService,
    private googleIdentity: GoogleIdentityService,
    private zone: NgZone,
    public router: Router,
    public activedRoute: ActivatedRoute,
  ) {
    // Solo en el navegador, después del primer render (compatible con SSR)
    afterNextRender(() => this.renderizarBotonGoogle());
  }

  ngOnInit(): void {
    if (this.authService.token && this.authService.user) {
      setTimeout(() => this.router.navigateByUrl('/'), 500);
      return;
    }

    this.activedRoute.queryParams.subscribe((resp: any) => {
      this.code_user = resp.code;
      // Solo rutas internas: evita redirecciones abiertas a dominios externos.
      this.redirect = resp.redirect?.startsWith('/') ? resp.redirect : '/';
    });

    if (this.code_user) {
      this.authService.verifiedAuth({ code_user: this.code_user }).subscribe((resp: any) => {
        if (resp.message == 403) {
          this.toastr.error('El código no pertenece a ningún usuario.', 'Verificación');
        }
        if (resp.message == 200) {
          this.toastr.success('Tu correo fue verificado. Ya podés iniciar sesión.', 'Cuenta verificada');
          setTimeout(() => this.router.navigateByUrl('/login'), 500);
        }
      });
    }
  }

  login(): void {
    if (this.procesando) return;

    if (!this.email.trim() || !this.password) {
      this.toastr.warning('Ingresá tu correo y tu contraseña.', 'Datos incompletos');
      return;
    }

    this.procesando = true;
    this.authService.login(this.email.trim(), this.password, this.remember).subscribe({
      next: (resp: any) =>
        this.procesarRespuestaLogin(
          resp,
          'Correo o contraseña incorrectos. Si tu cuenta es nueva, revisá tu correo para activarla.'
        ),
      error: (err) => this.manejarErrorLogin(err),
    });
  }

  /** Pide a Google que dibuje su botón dentro de #googleBtn. */
  private renderizarBotonGoogle(): void {
    if (!this.googleBtn) return;

    this.googleIdentity
      .renderizarBoton(this.googleBtn.nativeElement, environment.GOOGLE_CLIENT_ID, (credential) =>
        // El callback de Google corre fuera de Angular: se reingresa a la zona.
        this.zone.run(() => this.loginConGoogle(credential))
      )
      .catch((err) => console.error(err));
  }

  private loginConGoogle(credential: string): void {
    if (this.procesando) return;
    this.procesando = true;

    this.authService.loginGoogle(credential, this.remember).subscribe({
      next: (resp: any) =>
        this.procesarRespuestaLogin(resp, 'Esta cuenta de Google no tiene acceso a la tienda.'),
      error: (err) => this.manejarErrorLogin(err),
    });
  }

  /** Respuesta común para login con correo y con Google. */
  private procesarRespuestaLogin(resp: any, mensajeNoAutorizado: string): void {
    if (resp === true) {
      this.toastr.success('¡Bienvenido a la tienda!', 'Sesión iniciada');
      this.sincronizarCarrito();
      return;
    }

    this.procesando = false;

    if (resp?.status === 423) {
      this.toastr.error(
        resp.error?.error || 'Tu cuenta fue bloqueada. Contactá al soporte.',
        'Cuenta bloqueada'
      );
      return;
    }

    if (resp?.status === 401) {
      this.toastr.error(mensajeNoAutorizado, 'No se pudo ingresar');
      return;
    }

    this.toastr.error('No se pudo iniciar sesión. Intentá nuevamente.', 'Error');
  }

  private manejarErrorLogin(err: any): void {
    this.procesando = false;
    console.error('Error en el login:', err);
    this.toastr.error('No se pudo iniciar sesión. Intentá nuevamente.', 'Error');
  }

  /**
   * Vuelca el carrito del invitado (localStorage) al carrito del usuario
   * y recién ahí redirige. Si falla, igual redirige: la sesión ya existe.
   */
  private sincronizarCarrito(): void {
    this.carritoService.sincronizarTrasLogin().subscribe({
      next: (resp: any) => {
        if (resp?.omitidos?.length) {
          resp.omitidos.forEach((item: any) => this.toastr.warning(item.motivo, 'Carrito'));
        }
        this.procesando = false;
        this.router.navigateByUrl(this.redirect);
      },
      error: (err) => {
        console.error('No se pudo sincronizar el carrito:', err);
        this.procesando = false;
        this.router.navigateByUrl(this.redirect);
      },
    });
  }
}
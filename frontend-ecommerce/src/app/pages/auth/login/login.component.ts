import { Component, ElementRef, NgZone, ViewChild, afterNextRender } from '@angular/core';
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
  styleUrl: './login.component.css'
})
export class LoginComponent {

  email: string = '';
  password: string = '';
  code_user: string = '';

  /**
   * Ruta a la que volver después de iniciar sesión. La manda
   * carrito.component.ts cuando el invitado toca "Continuar con el pago"
   * (queryParams: { redirect: '/proceso-de-pago' }). Si no viene, home.
   */
  redirect: string = '/';

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
    // Se ejecuta solo en el navegador, después del primer render (compatible con SSR)
    afterNextRender(() => this.renderizarBotonGoogle());
  }

  ngOnInit(): void {
    if (this.authService.token && this.authService.user) {
      setTimeout(() => {
        this.router.navigateByUrl("/");
      }, 500);
      return;
    }

    this.activedRoute.queryParams.subscribe((resp: any) => {
      this.code_user = resp.code;
      // Se acepta solo una ruta interna: un redirect abierto permitiría
      // mandar al usuario a un dominio externo desde un link armado.
      this.redirect = resp.redirect?.startsWith('/') ? resp.redirect : '/';
    });

    if (this.code_user) {
      let data = {
        code_user: this.code_user,
      }

      this.authService.verifiedAuth(data).subscribe((resp: any) => {
        if (resp.message == 403) {
          this.toastr.error("validacion", "El codigo no pertenece a ningun usuario");
        }
        if (resp.message == 200) {
          this.toastr.success("Exito", "El correo ha sido verificado ingresar a la tienda");
          setTimeout(() => {
            this.router.navigateByUrl("/login");
          }, 500);
        }
      })
    }
  }

  login() {
    if (!this.email || !this.password) {
      this.toastr.error("validacion", "Necesitas ingresar todos los campos");
      return;
    }

    if (this.procesando) {
      return;
    }
    this.procesando = true;

    this.authService.login(this.email, this.password).subscribe({
      next: (resp: any) => this.procesarRespuestaLogin(
        resp,
        'Las credenciales son incorrectas. Si tu cuenta es nueva, revisa tu correo para verificarla.'
      ),
      error: (err) => this.manejarErrorLogin(err),
    });
  }

  /** Pide a Google que dibuje su botón dentro de #googleBtn. */
  private renderizarBotonGoogle(): void {
    if (!this.googleBtn) return;

    this.googleIdentity
      .renderizarBoton(this.googleBtn.nativeElement, environment.GOOGLE_CLIENT_ID, (credential) =>
        // El callback de Google se ejecuta fuera de Angular: se reingresa a la zona
        // para que funcionen la navegación y los toasts.
        this.zone.run(() => this.loginConGoogle(credential))
      )
      .catch((err) => console.error(err));
  }

  private loginConGoogle(credential: string): void {
    if (this.procesando) return;
    this.procesando = true;

    this.authService.loginGoogle(credential).subscribe({
      next: (resp: any) => this.procesarRespuestaLogin(resp, 'Esta cuenta de Google no tiene acceso a la tienda.'),
      error: (err) => this.manejarErrorLogin(err),
    });
  }

  /** Respuesta común para el login con email/contraseña y con Google. */
  private procesarRespuestaLogin(resp: any, mensajeNoAutorizado: string): void {
    if (resp === true) {
      this.toastr.success("Exito", 'Bienvenido a la tienda');
      this.sincronizarCarrito();
      return;
    }

    this.procesando = false;

    if (resp?.status === 423) {
      this.toastr.error("Cuenta bloqueada", resp.error?.error || "Tu cuenta fue bloqueada. Contacta al soporte.");
      return;
    }

    if (resp?.status === 401) {
      this.toastr.error("Validacion", mensajeNoAutorizado);
      return;
    }

    this.toastr.error("Error", 'No se pudo iniciar sesión. Intentá nuevamente.');
  }

  private manejarErrorLogin(err: any): void {
    this.procesando = false;
    console.error('Error en el login:', err);
    this.toastr.error("Error", 'No se pudo iniciar sesión. Intentá nuevamente.');
  }

  /**
   * Vuelca el carrito que el invitado armó en localStorage al carrito
   * persistente del usuario recién autenticado, y recién ahí redirige.
   *
   * La redirección va en los dos callbacks a propósito: si la
   * sincronización falla, el usuario igual ya inició sesión y no tiene
   * sentido dejarlo trabado en el formulario de login. El carrito se
   * vuelve a resolver solo en la próxima llamada a cargar().
   */
  private sincronizarCarrito(): void {
    this.carritoService.sincronizarTrasLogin().subscribe({
      next: (resp: any) => {
        // Excepción documentada del contrato: el backend informa en
        // "omitidos" los ítems que no pudo agregar o cuya cantidad topeó
        // por stock, para poder avisarle al usuario.
        if (resp?.omitidos?.length) {
          resp.omitidos.forEach((item: any) =>
            this.toastr.warning(item.motivo, 'Carrito')
          );
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

  showSuccess() {
    this.toastr.success('hellon word', 'Toastr fun');
  }
}
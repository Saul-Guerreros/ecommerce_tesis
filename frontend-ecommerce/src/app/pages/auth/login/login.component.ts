import { Component } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CarritoService } from '../../../core/services/carrito.service';

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

  constructor(
    private toastr: ToastrService,
    private authService: AuthService,
    private carritoService: CarritoService,
    public router: Router,
    public activedRoute: ActivatedRoute,
  ) {}

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
      next: (resp: any) => {

        if (resp && resp.status === 423) {
          this.procesando = false;
          this.toastr.error(
            "Cuenta bloqueada",
            resp.error?.error || "Tu cuenta fue bloqueada por demasiados intentos fallidos. Contacta al soporte."
          );
          return;
        }

        if (resp.error && resp.error.error == 'Unauthorized') {
          this.procesando = false;
          this.toastr.error(
            "Validacion",
            'Las credenciales son incorrectas. Si tu cuenta es nueva, revisa tu correo para verificarla.'
          );
          return;
        }

        if (resp == true) {
          this.toastr.success("Exito", 'Bienvenido a la tienda');
          this.sincronizarCarrito();
          return;
        }

        this.procesando = false;
      },
      error: (err) => {
        this.procesando = false;
        console.error('Error en el login:', err);
        this.toastr.error("Error", 'No se pudo iniciar sesión. Intentá nuevamente.');
      },
    });
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
import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { MatDialogRef } from '@angular/material/dialog';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../../../pages/auth/service/auth.service';
import { CarritoService } from '../../../core/services/carrito.service';

type Vista = 'login' | 'register';

/**
 * Modal de acceso para la pagina del carrito (botones "Iniciar Sesion" de
 * las tarjetas Contado y Credito). Vive sobre el carrito: no navega a
 * /login ni a /register, todo ocurre adentro del dialog.
 *
 * Resultado que devuelve al cerrarse (dialogRef.close(...)):
 *  - 'login'  -> se autentico con exito (login o registro+login futuro)
 *  - undefined -> el usuario cerro sin autenticarse
 */
@Component({
  selector: 'app-auth-modal',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './auth-modal.component.html',
  styleUrl: './auth-modal.component.css',
})
export class AuthModalComponent {

  vista: Vista = 'login';
  procesando = false;

  // ---- Login ----
  email = '';
  password = '';
  remember = false;

  // ---- Registro (mismos campos que RegisterComponent, sin cedula ni
  // fecha_nacimiento: se piden despues en el Checkout / Direccion de
  // Entrega, no en el alta de cuenta) ----
  nombre1 = '';
  nombre2 = '';
  apellido1 = '';
  apellido2 = '';
  celular = '';
  emailRegistro = '';
  passwordRegistro = '';
  passwordConfirmacion = '';
  terminos = false;

  constructor(
    private dialogRef: MatDialogRef<AuthModalComponent>,
    private authService: AuthService,
    private carritoService: CarritoService,
    private toastr: ToastrService,
  ) {}

  irARegistro(): void {
    this.vista = 'register';
  }

  irALogin(correoPrecargado?: string): void {
    this.vista = 'login';
    if (correoPrecargado) {
      this.email = correoPrecargado;
    }
  }

  cerrar(): void {
    this.dialogRef.close();
  }

  // ---------- Login ----------

  iniciarSesion(): void {
    if (!this.email || !this.password) {
      this.toastr.error('validacion', 'Necesitas ingresar todos los campos');
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
            'Cuenta bloqueada',
            resp.error?.error || 'Tu cuenta fue bloqueada por demasiados intentos fallidos. Contacta al soporte.'
          );
          return;
        }

        if (resp.error && resp.error.error == 'Unauthorized') {
          this.procesando = false;
          this.toastr.error(
            'Validacion',
            'Las credenciales son incorrectas. Si tu cuenta es nueva, revisa tu correo para verificarla.'
          );
          return;
        }

        if (resp == true) {
          // Fusiona lo que el invitado armo (si habia algo suelto en
          // localStorage) con el carrito persistido del usuario, igual que
          // LoginComponent. carrito$ se actualiza solo: CarritoComponent
          // ya esta suscripto, no hace falta recargar nada desde aca.
          this.carritoService.sincronizarTrasLogin().subscribe({
            next: (sinc: any) => {
              if (sinc?.omitidos?.length) {
                sinc.omitidos.forEach((item: any) => this.toastr.warning(item.motivo, 'Carrito'));
              }
              this.procesando = false;
              this.toastr.success('Exito', 'Bienvenido a la tienda');
              this.dialogRef.close('login');
            },
            error: () => {
              this.procesando = false;
              this.toastr.success('Exito', 'Bienvenido a la tienda');
              this.dialogRef.close('login');
            },
          });
          return;
        }

        this.procesando = false;
      },
      error: () => {
        this.procesando = false;
        this.toastr.error('Error', 'No se pudo iniciar sesión. Intentá nuevamente.');
      },
    });
  }

  // ---------- Registro ----------

  registrar(): void {
    if (!this.nombre1 || !this.apellido1 || !this.celular || !this.emailRegistro || !this.passwordRegistro) {
      this.toastr.error('validacion', 'Necesitas ingresar nombre, apellido, celular, email y contraseña');
      return;
    }
    if (this.passwordRegistro !== this.passwordConfirmacion) {
      this.toastr.error('validacion', 'Las contraseñas no coinciden');
      return;
    }
    if (!this.terminos) {
      this.toastr.error('validacion', 'Debes aceptar los Términos y Condiciones y la Política de Privacidad');
      return;
    }
    if (this.procesando) {
      return;
    }
    this.procesando = true;

    const data = {
      nombre1: this.nombre1,
      nombre2: this.nombre2 || null,
      apellido1: this.apellido1,
      apellido2: this.apellido2 || null,
      celular: this.celular,
      email: this.emailRegistro,
      password: this.passwordRegistro,
    };

    this.authService.register(data).subscribe({
      next: () => {
        this.procesando = false;
        this.toastr.success('Exito', 'Ingresá a tu correo para verificar tu cuenta y luego iniciá sesión aquí mismo');
        // La cuenta queda INACTIVA hasta verificar el correo (ver
        // AuthController::register): no hay sesion todavia, asi que el
        // modal NO se cierra como si hubiera "login exitoso" - se vuelve
        // a la vista de login, con el correo ya cargado.
        this.irALogin(this.emailRegistro);
      },
      error: (err: any) => {
        this.procesando = false;
        const errores = err?.error ? this.parseErrores(err.error) : null;
        this.toastr.error('validacion', errores || 'No se pudo completar el registro. Revisa los datos.');
      },
    });
  }

  private parseErrores(errorBody: any): string | null {
    try {
      const errores = typeof errorBody === 'string' ? JSON.parse(errorBody) : errorBody;
      const primerCampo = Object.keys(errores)[0];
      if (primerCampo && Array.isArray(errores[primerCampo])) {
        return errores[primerCampo][0];
      }
    } catch {
      // ignorar, se usa el mensaje generico
    }
    return null;
  }
}
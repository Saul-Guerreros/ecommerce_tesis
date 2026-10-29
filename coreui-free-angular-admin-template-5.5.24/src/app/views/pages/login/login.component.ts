import { Component, inject } from '@angular/core';
import { NgStyle, NgIf } from '@angular/common';
import { Router, RouterLink } from '@angular/router';

import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators
} from '@angular/forms';

import { IconDirective } from '@coreui/icons-angular';

import {
  ButtonDirective,
  CardBodyComponent,
  CardComponent,
  CardGroupComponent,
  ColComponent,
  ContainerComponent,
  FormControlDirective,
  FormDirective,
  InputGroupComponent,
  InputGroupTextDirective,
  RowComponent
} from '@coreui/angular';

import { AuthService } from '../../../auth/services/auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  standalone: true,
  imports: [
    ContainerComponent,
    RowComponent,
    ColComponent,
    CardGroupComponent,
    CardComponent,
    CardBodyComponent,
    FormDirective,
    InputGroupComponent,
    InputGroupTextDirective,
    IconDirective,
    FormControlDirective,
    ButtonDirective,
    NgStyle,
    NgIf,
    ReactiveFormsModule,
    RouterLink
  ]
})
export class LoginComponent {
 private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  /** Muestra u oculta lo que se escribió en el campo de contraseña. */
  verPassword = false;

  /** Iconos de ojo (el set gratuito de CoreUI Icons no los trae): [tamaño, trazados SVG]. */
  readonly iconoVer = [
    '24 24',
    '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>'
  ];
  readonly iconoOcultar = [
    '24 24',
    '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>'
  ];
  loading = false;
  errorMsg: string | null = null;
    /** Motivo exacto del rechazo: solo llega del backend en desarrollo (APP_DEBUG=true). */
  errorDetalle: string | null = null;

  loginForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]]
  });

  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.loading = true;
    this.errorMsg = null;
    this.errorDetalle = null;

    const { email, password } = this.loginForm.value;

    this.authService.login(email!, password!).subscribe({
      next: () => {
        this.loading = false;
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        this.loading = false;
        this.errorMsg = this.mensajeLogin(err);
        this.errorDetalle = err.error?.detalle ?? null;
      }
    });
  }

  /** Elige el mensaje según el "codigo" que devuelve el backend. */
  private mensajeLogin(err: any): string {
    switch (err.error?.codigo) {
      case 'CREDENCIALES_INVALIDAS':
        return 'Correo o contraseña incorrectos.';
      case 'SIN_ACCESO':
        return 'Tu cuenta no tiene acceso al panel de administración. Si sos cliente, ingresá desde la tienda.';
      case 'CUENTA_BLOQUEADA':
        return err.error?.error ?? 'Cuenta bloqueada. Contactá al administrador.';
    }

    if (err.status === 0) {
      return 'No se pudo conectar con el servidor. Verificá que la API esté en funcionamiento.';
    }
    return err.error?.error ?? err.error?.message ?? 'No se pudo iniciar sesión. Intentá nuevamente.';
  }

}



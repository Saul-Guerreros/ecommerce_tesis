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



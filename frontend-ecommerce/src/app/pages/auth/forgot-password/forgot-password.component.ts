import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';
import { CodeForgotPasswordComponent } from '../code-forgot-password/code-forgot-password.component';
import { NewPasswordComponent } from '../new-password/new-password.component';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, CodeForgotPasswordComponent, NewPasswordComponent],
  templateUrl: './forgot-password.component.html',
  styleUrl: './forgot-password.component.css',
})
export class ForgotPasswordComponent {
  isLoadingMail: any = null;
  isLoadingCode: any = null;

  email = '';
  code = '';
  enviando = false;

  constructor(
    public authService: AuthService,
    private toastr: ToastrService,
  ) {}

  /** 1: correo · 2: código · 3: nueva contraseña */
  get paso(): 1 | 2 | 3 {
    if (!this.isLoadingMail) return 1;
    return this.isLoadingCode ? 3 : 2;
  }

  get titulo(): string {
    return ['Recuperar contraseña', 'Verificá tu código', 'Nueva contraseña'][this.paso - 1];
  }

  get subtitulo(): string {
    return [
      'Ingresá el correo de tu cuenta y te enviaremos un código de verificación.',
      `Ingresá el código que enviamos a ${this.email}.`,
      'Elegí una contraseña nueva para tu cuenta.',
    ][this.paso - 1];
  }

  verifiedMail(): void {
    const email = this.email.trim();
    if (!email) {
      this.toastr.warning('Ingresá tu correo electrónico.', 'Dato requerido');
      return;
    }
    if (this.enviando) return;

    this.enviando = true;
    this.authService.verifiedMail({ email }).subscribe({
      next: (resp: any) => {
        this.enviando = false;
        if (resp?.message == 200) {
          this.isLoadingMail = 1;
          this.toastr.success('Te enviamos un código de verificación a tu correo.', 'Código enviado');
        } else {
          this.toastr.error('No encontramos una cuenta con ese correo.', 'Correo no registrado');
        }
      },
      error: () => {
        this.enviando = false;
        this.toastr.error('No se pudo enviar el código. Intentá nuevamente.', 'Error');
      },
    });
  }

  LoadingCode($event: any): void {
    this.isLoadingCode = $event;
  }

  CodeValueC($event: any): void {
    this.code = $event;
  }

  /** "Volver a enviar el código": regresa al paso 1 conservando el correo. */
  volverAlInicio(): void {
    this.isLoadingMail = null;
    this.isLoadingCode = null;
    this.code = '';
  }
}
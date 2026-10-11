import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';

@Component({
  selector: 'app-code-forgot-password',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './code-forgot-password.component.html',
  styleUrl: './code-forgot-password.component.css',
})
export class CodeForgotPasswordComponent {
  code = '';
  procesando = false;

  @Output() LoadingCodeStatus = new EventEmitter<any>();
  @Output() CodeValue = new EventEmitter<string>();
  /** El usuario pide reenviar el código: el padre vuelve al paso 1. */
  @Output() volver = new EventEmitter<void>();

  constructor(
    public authService: AuthService,
    private toastr: ToastrService,
  ) {}

  verifiedCode(): void {
    const code = this.code.trim();
    if (!code) {
      this.toastr.warning('Ingresá el código de verificación.', 'Dato requerido');
      return;
    }
    if (this.procesando) return;

    this.procesando = true;
    this.authService.verifiedCode({ code }).subscribe({
      next: (resp: any) => {
        this.procesando = false;
        if (resp?.message == 200) {
          this.CodeValue.emit(code);
          this.LoadingCodeStatus.emit(1);
          this.toastr.success('Código correcto. Ahora elegí tu nueva contraseña.', 'Código verificado');
        } else {
          this.toastr.error('El código no es válido o ya venció.', 'Código incorrecto');
        }
      },
      error: () => {
        this.procesando = false;
        this.toastr.error('No se pudo verificar el código. Intentá nuevamente.', 'Error');
      },
    });
  }
}
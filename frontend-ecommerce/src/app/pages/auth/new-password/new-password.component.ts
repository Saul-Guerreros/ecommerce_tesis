import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';

@Component({
  selector: 'app-new-password',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './new-password.component.html',
  styleUrl: './new-password.component.css',
})
export class NewPasswordComponent {
  @Input() code: any;

  new_password = '';
  confirmacion = '';
  procesando = false;

  constructor(
    public authService: AuthService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  verifiedNewPassword(): void {
    if (this.procesando) return;

    if (this.new_password.length < 8) {
      this.toastr.warning('La contraseña debe tener al menos 8 caracteres.', 'Revisá los datos');
      return;
    }
    if (this.new_password !== this.confirmacion) {
      this.toastr.warning('Las contraseñas no coinciden.', 'Revisá los datos');
      return;
    }

    this.procesando = true;
    this.authService.verifiedNewPassword({ new_password: this.new_password, code: this.code }).subscribe({
      next: () => {
        this.toastr.success('Tu contraseña se actualizó. Ya podés iniciar sesión.', 'Contraseña actualizada');
        this.router.navigateByUrl('/login');
      },
      error: () => {
        this.procesando = false;
        this.toastr.error('No se pudo actualizar la contraseña. Intentá nuevamente.', 'Error');
      },
    });
  }
}
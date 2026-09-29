import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { PerfilClienteService } from '../../../core/services/perfil-cliente.service';

@Component({
  selector: 'app-cambiar-password',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './cambiar-password.component.html',
  styleUrl: './cambiar-password.component.css',
})
export class CambiarPasswordComponent {
  guardando = false;

  password_actual = '';
  password_nuevo = '';
  password_nuevo_confirmation = '';

  constructor(
    private perfilClienteService: PerfilClienteService,
    private toastr: ToastrService,
  ) {}

  guardar(): void {
    if (this.password_nuevo.length < 8) {
      this.toastr.warning('La nueva contraseña debe tener al menos 8 caracteres.', 'Contraseña muy corta');
      return;
    }
    if (this.password_nuevo !== this.password_nuevo_confirmation) {
      this.toastr.warning('La confirmación no coincide.', 'Revisá la contraseña');
      return;
    }

    this.guardando = true;
    this.perfilClienteService
      .actualizarPassword({
        password_actual: this.password_actual,
        password_nuevo: this.password_nuevo,
        password_nuevo_confirmation: this.password_nuevo_confirmation,
      })
      .subscribe({
        next: (resp) => {
          this.guardando = false;
          this.toastr.success(resp.message, 'Contraseña actualizada');
          this.password_actual = '';
          this.password_nuevo = '';
          this.password_nuevo_confirmation = '';
        },
        error: (err) => {
          this.guardando = false;
          const mensaje = err.error?.message || 'No se pudo actualizar la contraseña.';
          this.toastr.error(mensaje, 'Error');
        },
      });
  }
}
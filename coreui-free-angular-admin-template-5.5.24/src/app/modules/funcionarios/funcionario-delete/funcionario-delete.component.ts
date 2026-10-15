import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { FuncionarioService } from '../service/funcionario.service';
import { Funcionario } from '../models/funcionario.model';

@Component({
  selector: 'app-funcionario-delete',
  standalone: true,
  imports: [CommonModule, ModalModule, ButtonModule, AlertModule, SpinnerModule],
  templateUrl: './funcionario-delete.component.html',
})
export class FuncionarioDeleteComponent {

  @Input() visible = false;
  @Input() funcionario: Funcionario | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() eliminado = new EventEmitter<number>();

  submitting = false;

  constructor(
    private service: FuncionarioService,
    private toastr: ToastrService,
  ) {}

  eliminar(): void {
    if (this.submitting || !this.funcionario) { return; }
    this.submitting = true;

    const id = this.funcionario.fun_id;

    this.service.destroy(id).subscribe({
      next: (resp) => {
        this.submitting = false;
        this.toastr.success(resp.message, 'Éxito');
        this.eliminado.emit(id);
        this.cerrar();
      },
      error: (err) => {
        this.submitting = false;
        if (err.status === 409) {
          this.toastr.warning(err.error.message, 'No permitido', { timeOut: 6000 });
          this.cerrar();
          return;
        }
        this.toastr.error(err.error?.message ?? 'Ocurrió un error inesperado', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

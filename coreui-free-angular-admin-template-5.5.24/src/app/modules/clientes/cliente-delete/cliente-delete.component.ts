import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { ClienteService } from '../service/cliente.service';
import { Cliente } from '../models/cliente.model';

@Component({
  selector: 'app-cliente-delete',
  standalone: true,
  imports: [CommonModule /* + módulos de CoreUI que uses */],
  templateUrl: './cliente-delete.component.html',
})
export class ClienteDeleteComponent {

  @Input() visible = false;
  @Input() cliente: Cliente | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() eliminado = new EventEmitter<number>();

  submitting = false;

  constructor(
    private clienteService: ClienteService,
    private toastr: ToastrService,
  ) {}

  eliminar(): void {
    if (this.submitting || !this.cliente) { return; }
    this.submitting = true;

    const id = this.cliente.cli_id;

    this.clienteService.destroy(id).subscribe({
      next: (resp) => {
        this.submitting = false;
        this.toastr.success(resp.message);
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
        this.toastr.error(err.error?.message ?? 'Ocurrió un error inesperado');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TipoDispositivo } from '../models/tipo-dispositivo.model';

@Component({
  selector: 'app-delete-tipo-dispositivo',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tipo-dispositivo.component.html',
})
export class DeleteTipoDispositivoComponent {
  @Input() visible = false;
  @Input() tipoDispositivo: TipoDispositivo | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.tipoDispositivo?.usos_count ?? 0) > 0;
  }

  get detalleUsos(): string {
    return `${this.tipoDispositivo?.usos_count ?? 0} registro(s) (fallas o solicitudes de servicio)`;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

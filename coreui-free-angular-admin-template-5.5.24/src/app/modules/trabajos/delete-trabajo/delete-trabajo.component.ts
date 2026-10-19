import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Trabajo } from '../models/trabajo.model';

@Component({
  selector: 'app-delete-trabajo',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-trabajo.component.html',
})
export class DeleteTrabajoComponent {
  @Input() visible = false;
  @Input() trabajo: Trabajo | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.trabajo?.usos_count ?? 0) > 0;
  }

  get detalleUsos(): string {
    return `${this.trabajo?.usos_count ?? 0} línea(s) de presupuesto`;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

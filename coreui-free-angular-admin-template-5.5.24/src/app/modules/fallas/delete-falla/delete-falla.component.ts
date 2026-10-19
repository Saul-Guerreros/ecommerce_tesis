import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Falla } from '../models/falla.model';

@Component({
  selector: 'app-delete-falla',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-falla.component.html',
})
export class DeleteFallaComponent {
  @Input() visible = false;
  @Input() falla: Falla | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.falla?.usos_count ?? 0) > 0;
  }

  get detalleUsos(): string {
    return `${this.falla?.usos_count ?? 0} diagnóstico(s)`;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

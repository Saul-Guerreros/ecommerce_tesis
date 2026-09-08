import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Barrio } from '../models/barrio.model';

@Component({
  selector: 'app-delete-barrio',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-barrio.component.html',
})
export class DeleteBarrioComponent {
  @Input() visible = false;
  @Input() barrio: Barrio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.barrio?.usos_count ?? 0) > 0;
  }

  get detalleUsos(): string {
    const d = this.barrio?.usos_detalle ?? {};
    return Object.keys(d).map((t) => `${d[t]} en ${t}`).join(', ');
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

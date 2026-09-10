import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Calle } from '../models/calle.model';

@Component({
  selector: 'app-delete-calle',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-calle.component.html',
})
export class DeleteCalleComponent {
  @Input() visible = false;
  @Input() calle: Calle | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.calle?.usos_count ?? 0) > 0;
  }

  get detalleUsos(): string {
    const d = this.calle?.usos_detalle ?? {};
    return Object.keys(d).map((t) => `${d[t]} en ${t}`).join(', ');
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

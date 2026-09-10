import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Ciudad } from '../models/ciudad.model';

@Component({
  selector: 'app-delete-ciudad',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-ciudad.component.html',
})
export class DeleteCiudadComponent {
  @Input() visible = false;
  @Input() ciudad: Ciudad | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.ciudad?.usos_count ?? 0) > 0;
  }

  get detalleUsos(): string {
    const d = this.ciudad?.usos_detalle ?? {};
    return Object.keys(d).map((t) => `${d[t]} en ${t}`).join(', ');
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { UnidadMedida } from '../models/unidad-medida.model';

@Component({
  selector: 'app-delete-unidad-medida',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-unidad-medida.component.html',
})
export class DeleteUnidadMedidaComponent {
  @Input() visible = false;
  @Input() unidadMedida: UnidadMedida | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.unidadMedida?.usos_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
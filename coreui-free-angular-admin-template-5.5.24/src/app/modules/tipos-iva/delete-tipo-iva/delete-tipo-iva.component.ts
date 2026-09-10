import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TipoIva } from '../models/tipo-iva.model';

@Component({
  selector: 'app-delete-tipo-iva',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tipo-iva.component.html',
})
export class DeleteTipoIvaComponent {
  @Input() visible = false;
  @Input() tipoIva: TipoIva | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.tipoIva?.usos_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TipoComprobante } from '../models/tipo-comprobante.model';

@Component({
  selector: 'app-delete-tipo-comprobante',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tipo-comprobante.component.html',
})
export class DeleteTipoComprobanteComponent {
  @Input() visible = false;
  @Input() tipoComprobante: TipoComprobante | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.tipoComprobante?.usos_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

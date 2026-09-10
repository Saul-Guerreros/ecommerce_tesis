import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TipoProveedor } from '../models/tipo-proveedor.model';

@Component({
  selector: 'app-delete-tipo-proveedor',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tipo-proveedor.component.html',
})
export class DeleteTipoProveedorComponent {
  @Input() visible = false;
  @Input() tipoProveedor: TipoProveedor | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.tipoProveedor?.usos_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
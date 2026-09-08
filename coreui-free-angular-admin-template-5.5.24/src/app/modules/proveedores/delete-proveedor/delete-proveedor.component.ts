import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Proveedor } from '../models/proveedor.model';

@Component({
  selector: 'app-delete-proveedor',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-proveedor.component.html',
})
export class DeleteProveedorComponent {
  @Input() visible = false;
  @Input() proveedor: Proveedor | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
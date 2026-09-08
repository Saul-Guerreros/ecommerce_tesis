import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Producto } from '../models/producto.model';

@Component({
  selector: 'app-delete-producto',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-producto.component.html',
})
export class DeleteProductoComponent {
  @Input() visible = false;
  @Input() producto: Producto | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.producto?.usos_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
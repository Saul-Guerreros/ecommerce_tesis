import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Sucursal } from '../models/sucursal.model';

@Component({
  selector: 'app-delete-sucursal',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-sucursal.component.html',
})
export class DeleteSucursalComponent {
  @Input() visible = false;
  @Input() sucursal: Sucursal | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get tieneDepositos(): boolean {
    return (this.sucursal?.depositos_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
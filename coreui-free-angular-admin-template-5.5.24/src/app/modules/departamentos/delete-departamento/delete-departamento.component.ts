import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Departamento } from '../models/departamento.model';

@Component({
  selector: 'app-delete-departamento',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-departamento.component.html',
})
export class DeleteDepartamentoComponent {
  @Input() visible = false;
  @Input() departamento: Departamento | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get tieneCiudades(): boolean {
    return (this.departamento?.ciudades_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
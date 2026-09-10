import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Empresa } from '../models/empresa.model';

@Component({
  selector: 'app-delete-empresa',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-empresa.component.html',
})
export class DeleteEmpresaComponent {
  @Input() visible = false;
  @Input() empresa: Empresa | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get tieneSucursales(): boolean {
    return (this.empresa?.sucursales_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

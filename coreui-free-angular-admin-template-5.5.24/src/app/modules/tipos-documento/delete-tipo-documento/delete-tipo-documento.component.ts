import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TipoDocumento } from '../models/tipo-documento.model';

@Component({
  selector: 'app-delete-tipo-documento',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tipo-documento.component.html',
})
export class DeleteTipoDocumentoComponent {
  @Input() visible = false;
  @Input() tipoDocumento: TipoDocumento | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.tipoDocumento?.usos_count ?? 0) > 0;
  }

  get protegido(): boolean {
    return !!this.tipoDocumento?.protegido;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

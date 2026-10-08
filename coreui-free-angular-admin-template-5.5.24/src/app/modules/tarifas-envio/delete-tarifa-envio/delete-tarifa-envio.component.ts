import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TarifaEnvio } from '../models/tarifa-envio.model';

@Component({
  selector: 'app-delete-tarifa-envio',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tarifa-envio.component.html',
})
export class DeleteTarifaEnvioComponent {
  @Input() visible = false;
  @Input() tarifa: TarifaEnvio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
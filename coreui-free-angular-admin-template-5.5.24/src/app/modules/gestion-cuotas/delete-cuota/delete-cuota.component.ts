import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { ConfiguracionCuota } from '../models/configuracion-cuota.model';

/** No elimina: emite el evento y el contenedor ejecuta la llamada. */
@Component({
  selector: 'app-delete-cuota',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-cuota.component.html',
})
export class DeleteCuotaComponent {
  @Input() visible = false;
  @Input() cuota: ConfiguracionCuota | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
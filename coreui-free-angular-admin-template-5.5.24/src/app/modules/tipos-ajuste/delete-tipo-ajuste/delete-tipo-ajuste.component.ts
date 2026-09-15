import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { TipoAjuste } from '../models/tipo-ajuste.model';

@Component({
  selector: 'app-delete-tipo-ajuste',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-tipo-ajuste.component.html',
})
export class DeleteTipoAjusteComponent {
  @Input() visible = false;
  @Input() tipoAjuste: TipoAjuste | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean { return (this.tipoAjuste?.usos_count ?? 0) > 0; }
  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
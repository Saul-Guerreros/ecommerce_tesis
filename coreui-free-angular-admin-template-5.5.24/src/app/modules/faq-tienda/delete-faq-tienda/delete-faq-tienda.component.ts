import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { FaqTienda } from '../models/faq-tienda.model';

@Component({
  selector: 'app-delete-faq-tienda',
  standalone: true,
  imports: [ButtonModule, ModalModule],
  templateUrl: './delete-faq-tienda.component.html',
})
export class DeleteFaqTiendaComponent {
  @Input() visible = false;
  @Input() faq: FaqTienda | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
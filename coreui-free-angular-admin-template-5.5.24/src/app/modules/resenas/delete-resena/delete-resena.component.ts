import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { ResenaAdmin } from '../models/resena.model';

@Component({
  selector: 'app-delete-resena',
  standalone: true,
  imports: [ButtonModule, ModalModule],
  templateUrl: './delete-resena.component.html',
})
export class DeleteResenaComponent {
  @Input() visible = false;
  @Input() resena: ResenaAdmin | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
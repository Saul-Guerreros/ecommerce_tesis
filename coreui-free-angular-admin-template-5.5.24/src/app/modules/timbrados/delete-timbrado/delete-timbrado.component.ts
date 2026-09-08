import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Timbrado } from '../models/timbrado.model';

@Component({
  selector: 'app-delete-timbrado',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-timbrado.component.html',
})
export class DeleteTimbradoComponent {
  @Input() visible = false;
  @Input() timbrado: Timbrado | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  // en_uso viene calculado del backend: tim_nroact != tim_nroini
  get enUso(): boolean {
    return this.timbrado?.en_uso ?? false;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
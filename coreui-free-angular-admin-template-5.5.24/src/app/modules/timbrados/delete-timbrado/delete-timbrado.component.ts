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

  get enUso(): boolean {
    return (this.timbrado?.usos_count ?? 0) > 0;
  }

  // Aunque no haya documentos en la tabla de ventas, el correlativo avanzado
  // indica numeración consumida: el backend también bloquea el borrado.
  get numeracionUsada(): boolean {
    if (!this.timbrado) return false;
    return (this.timbrado.tim_nroact ?? 0) >= (this.timbrado.tim_nroini ?? 1);
  }

  get bloqueado(): boolean {
    return this.enUso || this.numeracionUsada;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

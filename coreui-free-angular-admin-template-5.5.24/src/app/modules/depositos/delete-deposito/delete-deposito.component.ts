import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Deposito } from '../models/deposito.model';

@Component({
  selector: 'app-delete-deposito',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-deposito.component.html',
})
export class DeleteDepositoComponent {
  @Input() visible = false;
  @Input() deposito: Deposito | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  get enUso(): boolean {
    return (this.deposito?.usos_count ?? 0) > 0;
  }

  get esPrincipal(): boolean {
    return this.deposito?.dpo_princi === 1;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
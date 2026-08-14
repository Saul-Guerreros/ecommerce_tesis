import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Marca } from '../models/marca.model';

@Component({
  selector: 'app-delete-brand',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-brand.component.html',
  styleUrls: ['./delete-brand.component.scss'],
})
export class DeleteBrandComponent {
  @Input() visible = false;
  @Input() marca: Marca | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  confirmarEliminacion(): void {
    this.confirmar.emit();
  }
}
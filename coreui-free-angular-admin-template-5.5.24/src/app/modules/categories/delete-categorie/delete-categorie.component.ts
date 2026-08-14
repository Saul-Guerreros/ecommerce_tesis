import { Component, EventEmitter, Input, Output } from '@angular/core';
import { Categoria } from '../models/categoria.model';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';

@Component({
  selector: 'app-delete-categorie',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-categorie.component.html',
  styleUrl: './delete-categorie.component.scss',
})
export class DeleteCategorieComponent {
 @Input() visible = false;
  @Input() categoria: Categoria | null = null;
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

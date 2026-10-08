import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { CategoriaAtributo, SubcategoriaPlantilla } from '../models/categoria-atributo.model';

@Component({
  selector: 'app-delete-categoria-atributo',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-categoria-atributo.component.html',
})
export class DeleteCategoriaAtributoComponent {
  @Input() visible = false;
  @Input() item: CategoriaAtributo | null = null;
  @Input() categoria: SubcategoriaPlantilla | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

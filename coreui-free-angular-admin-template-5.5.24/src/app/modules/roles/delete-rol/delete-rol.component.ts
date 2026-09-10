import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule } from '@coreui/angular';
import { Rol } from '../models/rol.model';

@Component({
  selector: 'app-delete-rol',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule],
  templateUrl: './delete-rol.component.html',
})
export class DeleteRolComponent {
  @Input() visible = false;
  @Input() rol: Rol | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<void>();

  private readonly PROTEGIDOS = ['Administrador', 'Vendedor', 'Cliente'];

  get esProtegido(): boolean {
    return !!this.rol && this.PROTEGIDOS.includes(this.rol.rol_descri);
  }

  get enUso(): boolean {
    return (this.rol?.usuarios_count ?? 0) > 0;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
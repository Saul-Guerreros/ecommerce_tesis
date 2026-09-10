import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { UsuariosRoutingModule } from './usuarios-routing.module';
import { UsuariosComponent } from './usuarios.component';
import { ListUsuarioComponent } from './list-usuario/list-usuario.component';
import { FormUsuarioComponent } from './form-usuario/form-usuario.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, UsuariosRoutingModule, UsuariosComponent, ListUsuarioComponent, FormUsuarioComponent],
})
export class UsuariosModule {}
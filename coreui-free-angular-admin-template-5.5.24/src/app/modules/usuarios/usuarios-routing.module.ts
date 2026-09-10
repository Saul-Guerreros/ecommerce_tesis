import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { UsuariosComponent } from './usuarios.component';
import { ListUsuarioComponent } from './list-usuario/list-usuario.component';

const routes: Routes = [
  { path: '', component: UsuariosComponent, children: [{ path: '', component: ListUsuarioComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class UsuariosRoutingModule {}
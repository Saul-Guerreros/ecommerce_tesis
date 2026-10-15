import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposClienteComponent } from './tipos-cliente.component';
import { ListTipoClienteComponent } from './list-tipo-cliente/list-tipo-cliente.component';

const routes: Routes = [
  { path: '', component: TiposClienteComponent, children: [{ path: '', component: ListTipoClienteComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class TiposClienteRoutingModule {}
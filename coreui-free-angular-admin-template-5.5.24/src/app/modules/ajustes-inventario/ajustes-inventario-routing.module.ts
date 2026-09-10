import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AjustesInventarioComponent } from './ajustes-inventario.component';
import { ListAjusteComponent } from './list-ajuste/list-ajuste.component';

const routes: Routes = [
  { path: '', component: AjustesInventarioComponent, children: [{ path: '', component: ListAjusteComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class AjustesInventarioRoutingModule {}
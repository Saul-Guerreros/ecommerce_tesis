import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposAjusteComponent } from './tipos-ajuste.component';
import { ListTipoAjusteComponent } from './list-tipo-ajuste/list-tipo-ajuste.component';

const routes: Routes = [
  { path: '', component: TiposAjusteComponent, children: [{ path: '', component: ListTipoAjusteComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class TiposAjusteRoutingModule {}
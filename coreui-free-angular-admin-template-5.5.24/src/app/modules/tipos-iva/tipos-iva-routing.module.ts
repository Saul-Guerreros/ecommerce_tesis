import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposIvaComponent } from './tipos-iva.component';
import { ListTipoIvaComponent } from './list-tipo-iva/list-tipo-iva.component';

const routes: Routes = [
  {
    path: '',
    component: TiposIvaComponent,
    children: [{ path: '', component: ListTipoIvaComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class TiposIvaRoutingModule {}

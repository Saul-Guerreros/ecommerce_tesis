import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposComprobanteComponent } from './tipos-comprobante.component';
import { ListTipoComprobanteComponent } from './list-tipo-comprobante/list-tipo-comprobante.component';

const routes: Routes = [
  {
    path: '',
    component: TiposComprobanteComponent,
    children: [{ path: '', component: ListTipoComprobanteComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class TiposComprobanteRoutingModule {}

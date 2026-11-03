import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { NotasCreditoVentaComponent } from './notas-credito-venta.component';
import { ListNotaCreditoVentaComponent } from './list-nota-credito-venta/list-nota-credito-venta.component';

const routes: Routes = [
  {
    path: '',
    component: NotasCreditoVentaComponent,
    children: [{ path: '', component: ListNotaCreditoVentaComponent }],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class NotasCreditoVentaRoutingModule {}
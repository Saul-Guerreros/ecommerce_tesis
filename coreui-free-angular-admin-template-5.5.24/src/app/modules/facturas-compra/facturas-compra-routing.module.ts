import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FacturasCompraComponent } from './facturas-compra.component';
import { ListCompraComponent } from './list-compra/list-compra.component';

const routes: Routes = [
  { path: '', component: FacturasCompraComponent, children: [{ path: '', component: ListCompraComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class FacturasCompraRoutingModule {}
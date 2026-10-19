import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FacturasVentaComponent } from './facturas-venta.component';
import { ListVentaComponent } from './list-venta/list-venta.component';

const routes: Routes = [
  { path: '', component: FacturasVentaComponent, children: [{ path: '', component: ListVentaComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class FacturasVentaRoutingModule {}
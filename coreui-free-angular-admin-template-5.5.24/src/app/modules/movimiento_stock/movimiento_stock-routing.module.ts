import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { KardexComponent } from './movimiento_stock.component';
import { ListKardexComponent } from './list-movimiento_stock/list-movimiento_stock.component';

const routes: Routes = [
  { path: '', component: KardexComponent, children: [{ path: '', component: ListKardexComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class KardexRoutingModule {}
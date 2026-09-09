import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { StockActualComponent } from './stock-actual.component';
import { ListStockComponent } from './list-stock/list-stock.component';

const routes: Routes = [
  { path: '', component: StockActualComponent, children: [{ path: '', component: ListStockComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class StockActualRoutingModule {}
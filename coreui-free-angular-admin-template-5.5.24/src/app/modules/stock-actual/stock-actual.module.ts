import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { StockActualRoutingModule } from './stock-actual-routing.module';
import { StockActualComponent } from './stock-actual.component';
import { ListStockComponent } from './list-stock/list-stock.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, StockActualRoutingModule, StockActualComponent, ListStockComponent],
})
export class StockActualModule {}
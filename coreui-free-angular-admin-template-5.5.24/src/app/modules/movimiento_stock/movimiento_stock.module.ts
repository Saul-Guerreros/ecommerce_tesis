import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { KardexRoutingModule } from './movimiento_stock-routing.module';
import { KardexComponent } from './movimiento_stock.component';
import { ListKardexComponent } from './list-movimiento_stock/list-movimiento_stock.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, KardexRoutingModule, KardexComponent, ListKardexComponent],
})
export class KardexModule {}


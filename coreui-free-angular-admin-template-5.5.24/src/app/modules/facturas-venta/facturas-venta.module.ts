import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FacturasVentaRoutingModule } from './facturas-venta-routing.module';
import { FacturasVentaComponent } from './facturas-venta.component';
import { ListVentaComponent } from './list-venta/list-venta.component';
import { VerVentaComponent } from './ver-venta/ver-venta.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, FacturasVentaRoutingModule, FacturasVentaComponent,
    ListVentaComponent, VerVentaComponent,
  ],
})
export class FacturasVentaModule {}
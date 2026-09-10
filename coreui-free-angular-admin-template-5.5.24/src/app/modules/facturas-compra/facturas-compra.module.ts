import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FacturasCompraRoutingModule } from './facturas-compra-routing.module';
import { FacturasCompraComponent } from './facturas-compra.component';
import { ListCompraComponent } from './list-compra/list-compra.component';
import { FormCompraComponent } from './form-compra/form-compra.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, FacturasCompraRoutingModule, FacturasCompraComponent,
    ListCompraComponent, FormCompraComponent,
  ],
})
export class FacturasCompraModule {}
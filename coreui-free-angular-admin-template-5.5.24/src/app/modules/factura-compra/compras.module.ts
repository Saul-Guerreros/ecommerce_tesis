import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ComprasRoutingModule } from './compras-routing.module';
import { ComprasComponent } from './compras.component';
import { ListCompraComponent } from './list-compra/list-compra.component';
import { FormCompraComponent } from './form-compra/form-compra.component';
import { AnularCompraComponent } from './anular-compra/anular-compra.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, ComprasRoutingModule, ComprasComponent,
    ListCompraComponent, FormCompraComponent, AnularCompraComponent,
  ],
})
export class ComprasModule {}
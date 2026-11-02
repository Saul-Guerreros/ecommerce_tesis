import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NotasCreditoVentaRoutingModule } from './notas-credito-venta-routing.module';
import { NotasCreditoVentaComponent } from './notas-credito-venta.component';
import { ListNotaCreditoVentaComponent } from './list-nota-credito-venta/list-nota-credito-venta.component';
import { FormNotaCreditoVentaComponent } from './form-nota-credito-venta/form-nota-credito-venta.component';
import { VerNotaCreditoVentaComponent } from './ver-nota-credito-venta/ver-nota-credito-venta.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, NotasCreditoVentaRoutingModule, NotasCreditoVentaComponent,
    ListNotaCreditoVentaComponent, FormNotaCreditoVentaComponent, VerNotaCreditoVentaComponent,
  ],
})
export class NotasCreditoVentaModule {}
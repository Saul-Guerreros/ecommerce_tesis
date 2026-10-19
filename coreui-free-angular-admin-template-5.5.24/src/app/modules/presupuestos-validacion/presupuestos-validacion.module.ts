import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PresupuestosValidacionRoutingModule } from './presupuestos-validacion-routing.module';
import { PresupuestosValidacionComponent } from './presupuestos-validacion.component';
import { ListPresupuestoComponent } from './list-presupuesto/list-presupuesto.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, PresupuestosValidacionRoutingModule, PresupuestosValidacionComponent, ListPresupuestoComponent],
})
export class PresupuestosValidacionModule {}

import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PresupuestosValidacionComponent } from './presupuestos-validacion.component';
import { ListPresupuestoComponent } from './list-presupuesto/list-presupuesto.component';

const routes: Routes = [
  {
    path: '',
    component: PresupuestosValidacionComponent,
    children: [{ path: '', component: ListPresupuestoComponent, data: { title: 'Validación de presupuestos' } }],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class PresupuestosValidacionRoutingModule {}

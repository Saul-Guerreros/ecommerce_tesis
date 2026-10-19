import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { OrdenesTecnicoComponent } from './ordenes-tecnico.component';
import { ListOrdenesComponent } from './list-ordenes/list-ordenes.component';
import { OrdenTrabajoComponent } from './orden-trabajo/orden-trabajo.component';

const routes: Routes = [
  {
    path: '',
    component: OrdenesTecnicoComponent,
    children: [
      { path: '', component: ListOrdenesComponent, data: { title: 'Mis órdenes' } },
      { path: ':id', component: OrdenTrabajoComponent, data: { title: 'Orden de trabajo' } },
    ],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class OrdenesTecnicoRoutingModule {}

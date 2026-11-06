import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CobrosServicioComponent } from './cobros-servicio.component';
import { ListCobroComponent } from './list-cobro/list-cobro.component';

const routes: Routes = [
  {
    path: '',
    component: CobrosServicioComponent,
    children: [{ path: '', component: ListCobroComponent, data: { title: 'Cobros de servicios' } }],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class CobrosServicioRoutingModule {}

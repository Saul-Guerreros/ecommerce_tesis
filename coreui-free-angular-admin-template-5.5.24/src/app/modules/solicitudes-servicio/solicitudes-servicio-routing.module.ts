import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SolicitudesServicioComponent } from './solicitudes-servicio.component';
import { ListSolicitudComponent } from './list-solicitud/list-solicitud.component';

const routes: Routes = [
  {
    path: '',
    component: SolicitudesServicioComponent,
    children: [{ path: '', component: ListSolicitudComponent, data: { title: 'Bandeja de solicitudes' } }],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class SolicitudesServicioRoutingModule {}

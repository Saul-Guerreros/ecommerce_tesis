import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SolicitudesServicioComponent } from './solicitudes-servicio.component';
import { ListSolicitudComponent } from './list-solicitud/list-solicitud.component';
import { MostradorSolicitudComponent } from './mostrador-solicitud/mostrador-solicitud.component';

const routes: Routes = [
  {
    path: '',
    component: SolicitudesServicioComponent,
    children: [
      { path: '', component: ListSolicitudComponent, data: { title: 'Bandeja de solicitudes' } },
      // Ingreso presencial: subruta de /servicios/solicitudes (PERMISO_POR_RUTA ya la cubre).
      { path: 'mostrador', component: MostradorSolicitudComponent, data: { title: 'Nueva solicitud en mostrador' } },
    ],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class SolicitudesServicioRoutingModule {}

import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SolicitudesServicioRoutingModule } from './solicitudes-servicio-routing.module';
import { SolicitudesServicioComponent } from './solicitudes-servicio.component';
import { ListSolicitudComponent } from './list-solicitud/list-solicitud.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, SolicitudesServicioRoutingModule, SolicitudesServicioComponent, ListSolicitudComponent],
})
export class SolicitudesServicioModule {}

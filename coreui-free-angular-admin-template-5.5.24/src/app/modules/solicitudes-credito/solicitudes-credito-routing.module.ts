import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { SolicitudesCreditoComponent } from './solicitudes-credito.component';
import { ListSolicitudCreditoComponent } from './list-solicitud-credito/list-solicitud-credito.component';
import { DetalleSolicitudCreditoComponent } from './detalle-solicitud-credito/detalle-solicitud-credito.component';

const routes: Routes = [
  {
    path: '',
    component: SolicitudesCreditoComponent,
    children: [
      { path: '', component: ListSolicitudCreditoComponent, data: { title: 'Solicitudes de crédito' } },
      { path: ':id', component: DetalleSolicitudCreditoComponent, data: { title: 'Gestión de la solicitud' } },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class SolicitudesCreditoRoutingModule {}
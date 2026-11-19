import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SolicitudesCreditoRoutingModule } from './solicitudes-credito-routing.module';
import { SolicitudesCreditoComponent } from './solicitudes-credito.component';
import { ListSolicitudCreditoComponent } from './list-solicitud-credito/list-solicitud-credito.component';
import { DetalleSolicitudCreditoComponent } from './detalle-solicitud-credito/detalle-solicitud-credito.component';

/**
 * Los componentes son standalone, por eso van en imports y no en
 * declarations (mismo patrón que PedidosModule). El modal de cambio de
 * plan se importa dentro de DetalleSolicitudCreditoComponent.
 */
@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    SolicitudesCreditoRoutingModule,
    SolicitudesCreditoComponent,
    ListSolicitudCreditoComponent,
    DetalleSolicitudCreditoComponent,
  ],
})
export class SolicitudesCreditoModule {}
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { PedidosRoutingModule } from './pedidos-routing.module';
import { PedidosComponent } from './pedidos.component';
import { ListPedidoComponent } from './list-pedido/list-pedido.component';
import { DetallePedidoComponent } from './detalle-pedido/detalle-pedido.component';

/**
 * Los componentes son standalone, por eso van en imports y no en
 * declarations (mismo patrón que KardexModule y UsuariosModule).
 * Los modales se importan dentro de DetallePedidoComponent.
 */
@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    PedidosRoutingModule,
    PedidosComponent,
    ListPedidoComponent,
    DetallePedidoComponent,
  ],
})
export class PedidosModule {}
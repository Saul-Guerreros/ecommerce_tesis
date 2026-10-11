import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { PedidosComponent } from './pedidos.component';
import { ListPedidoComponent } from './list-pedido/list-pedido.component';
import { DetallePedidoComponent } from './detalle-pedido/detalle-pedido.component';

const routes: Routes = [
  {
    path: '',
    component: PedidosComponent,
    children: [
      { path: '', component: ListPedidoComponent, data: { title: 'Pedidos' } },
      { path: ':id', component: DetallePedidoComponent, data: { title: 'Gestión del pedido' } },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PedidosRoutingModule {}
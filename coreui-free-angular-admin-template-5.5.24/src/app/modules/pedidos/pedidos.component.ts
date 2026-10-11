import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Contenedor del módulo. Solo expone el router-outlet para que el listado
 * y, más adelante, el detalle del pedido se rendericen como hijos.
 */
@Component({
  selector: 'app-pedidos',
  standalone: true,
  imports: [RouterOutlet],
  template: '<router-outlet></router-outlet>',
})
export class PedidosComponent {}
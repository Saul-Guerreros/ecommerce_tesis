import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Contenedor del módulo. Solo expone el router-outlet para que el
 * listado y el detalle se rendericen como hijos.
 */
@Component({
  selector: 'app-solicitudes-credito',
  standalone: true,
  imports: [RouterOutlet],
  template: '<router-outlet></router-outlet>',
})
export class SolicitudesCreditoComponent {}
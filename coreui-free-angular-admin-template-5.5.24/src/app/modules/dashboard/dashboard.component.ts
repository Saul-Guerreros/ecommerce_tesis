import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/** Contenedor del módulo: solo expone el router-outlet. */
@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [RouterOutlet],
  template: '<router-outlet></router-outlet>',
})
export class DashboardComponent {}
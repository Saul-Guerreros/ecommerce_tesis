import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/** Contenedor de la sección Reportes. */
@Component({
  selector: 'app-reportes',
  standalone: true,
  imports: [RouterOutlet],
  template: '<router-outlet></router-outlet>',
})
export class ReportesComponent {}
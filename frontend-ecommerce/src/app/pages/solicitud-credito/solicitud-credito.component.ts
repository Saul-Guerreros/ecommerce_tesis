import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter, map, startWith } from 'rxjs';

type PasoWizard = 'personal' | 'laboral' | 'referencias' | 'confirmacion';

/**
 * Contenedor del wizard de Solicitud de Crédito. Mismo patrón que
 * CheckoutComponent: pinta la barra de pasos según la URL actual (vía
 * router.events) y deja el contenido de cada paso al <router-outlet>,
 * sin estado propio del formulario -- eso vive en cada componente hijo
 * y en CreditoService.
 *
 * Los SUB-PASOS (Datos Personales 1/3, 2/3, 3/3, etc.) NO generan una
 * ruta propia: son estado interno de cada componente de paso. La barra
 * de acá solo conoce los 4 pasos del encabezado del instructivo.
 */
@Component({
  selector: 'app-solicitud-credito',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './solicitud-credito.component.html',
  styleUrl: './solicitud-credito.component.css',
})
export class SolicitudCreditoComponent {
  pasoActual: PasoWizard = 'personal';

  constructor(private router: Router) {
    this.router.events
      .pipe(
        filter((e): e is NavigationEnd => e instanceof NavigationEnd),
        map((e) => e.urlAfterRedirects),
        startWith(this.router.url)
      )
      .subscribe((url) => {
        if (url.includes('/laboral')) this.pasoActual = 'laboral';
        else if (url.includes('/referencias')) this.pasoActual = 'referencias';
        else if (url.includes('/confirmacion')) this.pasoActual = 'confirmacion';
        else this.pasoActual = 'personal';
      });
  }
}
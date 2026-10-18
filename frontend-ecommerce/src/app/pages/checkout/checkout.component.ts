import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter, map, startWith } from 'rxjs';

/**
 * Contenedor del checkout: sólo dibuja el indicador de pasos.
 *
 * El paso 3 pasó de "Confirmación" a "Pago": el pedido ahora se confirma
 * en el paso 2 y lo que queda después es declarar con qué se paga.
 */
@Component({
  selector: 'app-checkout',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './checkout.component.html',
  styleUrl: './checkout.component.css',
})
export class CheckoutComponent {
  pasoActual: 'entrega' | 'facturacion' | 'pago' = 'entrega';

  constructor(private router: Router) {
    this.router.events
      .pipe(
        filter((e): e is NavigationEnd => e instanceof NavigationEnd),
        map((e) => e.urlAfterRedirects),
        startWith(this.router.url),
      )
      .subscribe((url) => {
        if (url.includes('/pago')) this.pasoActual = 'pago';
        else if (url.includes('/facturacion')) this.pasoActual = 'facturacion';
        else this.pasoActual = 'entrega';
      });
  }
}
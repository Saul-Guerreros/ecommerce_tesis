import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter, map, startWith } from 'rxjs';

/**
 * Contenedor del checkout a crédito: dibuja el indicador de 2 pasos.
 * No hay paso de pago: el crédito se paga en cuotas, y el pedido se crea
 * al confirmar el Paso 2.
 */
@Component({
  selector: 'app-checkout-credito',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './checkout-credito.component.html',
  styleUrl: '../checkout/checkout.component.css',
})
export class CheckoutCreditoComponent {
  pasoActual: 'entrega' | 'facturacion' = 'entrega';

  constructor(private router: Router) {
    this.router.events
      .pipe(
        filter((e): e is NavigationEnd => e instanceof NavigationEnd),
        map((e) => e.urlAfterRedirects),
        startWith(this.router.url),
      )
      .subscribe((url) => {
        this.pasoActual = url.includes('/facturacion') ? 'facturacion' : 'entrega';
      });
  }
}
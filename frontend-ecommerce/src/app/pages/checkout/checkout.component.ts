import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter, map, startWith } from 'rxjs';

@Component({
  selector: 'app-checkout',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './checkout.component.html',
  styleUrl: './checkout.component.css',
})
export class CheckoutComponent {
  pasoActual: 'entrega' | 'facturacion' | 'confirmacion' = 'entrega';

  constructor(private router: Router) {
    this.router.events
      .pipe(
        filter((e): e is NavigationEnd => e instanceof NavigationEnd),
        map((e) => e.urlAfterRedirects),
        startWith(this.router.url),
      )
      .subscribe((url) => {
        if (url.includes('/facturacion')) this.pasoActual = 'facturacion';
        else if (url.includes('/confirmacion')) this.pasoActual = 'confirmacion';
        else this.pasoActual = 'entrega';
      });
  }
}
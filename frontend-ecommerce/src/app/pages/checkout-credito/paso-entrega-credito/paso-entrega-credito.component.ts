import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { CheckoutCreditoContexto } from '../../../core/models/credito.model';
import { CreditoService } from '../../../core/services/credito.service';
import { DatosEntregaCardComponent } from '../../../shared/components/datos-entrega-card/datos-entrega-card.component';
import { ProductosCarritoCardComponent } from '../../../shared/components/productos-carrito-card/productos-carrito-card.component';

/**
 * Paso 1 del checkout a crédito: Entrega.
 *
 * No hay nada que elegir: el crédito se retira en casa matriz y el receptor
 * es el titular de la solicitud. La pantalla muestra lo que el backend ya
 * fijó, para que el cliente lo revise antes de seguir.
 */
@Component({
  selector: 'app-paso-entrega-credito',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule, DatosEntregaCardComponent, ProductosCarritoCardComponent],
  templateUrl: './paso-entrega-credito.component.html',
  styleUrl: '../../checkout/paso-facturacion/paso-facturacion.component.css',
})
export class PasoEntregaCreditoComponent implements OnInit {
  ctx: CheckoutCreditoContexto | null = null;
  cargando = true;

  private idSolicitud = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private creditoService: CreditoService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    // El :id está en la ruta padre (checkout-credito/:id).
    this.idSolicitud = Number(this.route.parent?.snapshot.paramMap.get('id'));

    this.creditoService.checkoutContexto(this.idSolicitud).subscribe({
      next: (resp) => {
        this.ctx = resp.data;
        this.cargando = false;
      },
      error: (err) => {
        // 409 / 404: la solicitud no está lista para este paso (venció,
        // ya generó su pedido o no es del cliente). Se vuelve al detalle.
        this.toastr.warning(err.error?.message ?? 'No pudimos abrir el checkout de esta solicitud.', 'Checkout');
        this.router.navigate(['/mis-solicitudes-credito'], { replaceUrl: true });
      },
    });
  }

  textoDias(dias: number | null | undefined): string {
    if (dias === null || dias === undefined) return '';
    if (dias === 0) return 'vence hoy';
    return dias === 1 ? 'queda 1 día' : `quedan ${dias} días`;
  }

  continuar(): void {
    this.router.navigate(['/checkout-credito', this.idSolicitud, 'facturacion']);
  }

  volver(): void {
    this.router.navigate(['/mis-solicitudes-credito', this.idSolicitud]);
  }
}
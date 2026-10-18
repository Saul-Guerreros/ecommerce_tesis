import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { PedidoDetalle } from '../../core/models/pedido-cliente.model';
import { PedidoClienteService } from '../../core/services/pedido-cliente.service';
import { DatosEntregaCardComponent } from '../../shared/components/datos-entrega-card/datos-entrega-card.component';

/**
 * Detalle de un pedido dentro de "Mis Pedidos".
 *
 * Distinto de pedido-resultado (la pantalla que sigue justo después de
 * pagar): éste es de consulta general, para cualquier pedido en cualquier
 * estado, y se llega acá sólo desde el listado — no es parte del flujo
 * del checkout.
 */
@Component({
  selector: 'app-pedido-detalle',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DatePipe, RouterModule, DatosEntregaCardComponent],
  templateUrl: './pedido-detalle.component.html',
  styleUrl: './pedido-detalle.component.css',
})
export class PedidoDetalleComponent implements OnInit {
  numero = '';
  pedido!: PedidoDetalle;
  cargando = true;

  constructor(
    private servicio: PedidoClienteService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    this.servicio.detalle(this.numero).subscribe({
      next: (resp) => {
        this.pedido = resp.data;
        this.cargando = false;
      },
      error: (e) => {
        this.cargando = false;
        this.toastr.error(e.error?.message ?? 'No encontramos ese pedido.', 'Error');
        this.router.navigate(['/mis-pedidos'], { replaceUrl: true });
      },
    });
  }

  restante(): string {
    if (!this.pedido?.ped_vence) return '';

    const faltan = new Date(this.pedido.ped_vence).getTime() - Date.now();
    if (faltan <= 0) return 'vencido';

    const horas = Math.floor(faltan / 3_600_000);
    const minutos = Math.floor((faltan % 3_600_000) / 60_000);

    return horas > 0 ? `${horas} h ${minutos} min` : `${minutos} min`;
  }

  pagarAhora(): void {
    this.router.navigate(['/proceso-de-pago/pago', this.numero]);
  }

  /**
   * No es un window.open(url, '_blank') directo: ese endpoint exige
   * sesión, y una navegación directa del navegador no lleva el token que
   * Angular adjunta en los pedidos por HttpClient. Por eso el archivo se
   * pide primero como Blob (con el token puesto) y recién ese Blob se
   * abre en una pestaña nueva — ahí sí se ve el comprobante en vez de un
   * error de autenticación.
   */
  abrirComprobante(url: string | null): void {
    if (!url) return;

    this.servicio.descargarArchivo(url).subscribe({
      next: (blob) => {
        const blobUrl = URL.createObjectURL(blob);
        window.open(blobUrl, '_blank');
        // Se libera un poco después de abrir: el navegador ya tomó lo que
        // necesitaba para mostrarlo en la pestaña nueva.
        setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000);
      },
      error: () => {
        this.toastr.error('No pudimos abrir el comprobante.', 'Error');
      },
    });
  }

  volver(): void {
    this.router.navigate(['/mis-pedidos']);
  }
}
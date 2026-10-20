import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, Input, OnChanges, OnDestroy, OnInit, SimpleChanges } from '@angular/core';
import { Router } from '@angular/router';
import { PedidoPendiente } from '../../../core/models/pedido-cliente.model';
import { PedidoClienteService } from '../../../core/services/pedido-cliente.service';

/**
 * Banner del header: "Tenés un pedido pendiente de pago".
 *
 * Es la pieza que le faltaba al rediseño del checkout. Si el cliente
 * confirma un pedido y cierra el navegador antes de pagar, no tiene
 * ningún otro camino para reencontrar el link de pago salvo entrar a
 * "Mis Pedidos" él mismo — esto evita que tenga que acordarse de hacerlo.
 *
 * Sólo se muestra con sesión iniciada. El header lo controla con
 * *ngIf="estaLogueado" para no disparar la consulta en cada carga de
 * página a un visitante anónimo.
 */
@Component({
  selector: 'app-pedido-pendiente-banner',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './pedido-pendiente-banner.component.html',
  styleUrl: './pedido-pendiente-banner.component.css',
})
export class PedidoPendienteBannerComponent implements OnInit, OnChanges, OnDestroy {
  /** El header lo marca en true/false según haya sesión. */
  @Input() activo = false;

  pendiente: PedidoPendiente | null = null;
  cerrado = false;

  private reloj?: ReturnType<typeof setInterval>;

  constructor(
    private servicio: PedidoClienteService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (this.activo) this.consultar();
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['activo'] && !cambios['activo'].firstChange) {
      if (this.activo) {
        this.cerrado = false;
        this.consultar();
      } else {
        this.pendiente = null;
        this.detenerReloj();
      }
    }
  }

  ngOnDestroy(): void {
    this.detenerReloj();
  }

  private consultar(): void {
    this.servicio.pendiente().subscribe({
      next: (resp) => {
        this.pendiente = resp.data;

        // Se refresca cada 2 minutos: si el cliente deja la pestaña
        // abierta hasta que vence, el banner se retira solo.
        this.detenerReloj();
        this.reloj = setInterval(() => this.servicio.pendiente().subscribe({
          next: (r) => { this.pendiente = r.data; },
        }), 120_000);
      },
      // Un fallo acá no es grave: el banner es un recordatorio, no una
      // funcionalidad crítica. Falla en silencio.
      error: () => {},
    });
  }

  private detenerReloj(): void {
    if (this.reloj) clearInterval(this.reloj);
  }

  restante(): string {
    if (!this.pendiente) return '';

    const faltan = new Date(this.pendiente.ped_vence).getTime() - Date.now();
    if (faltan <= 0) return 'está por vencer';

    const horas = Math.floor(faltan / 3_600_000);
    const minutos = Math.floor((faltan % 3_600_000) / 60_000);

    return horas > 0 ? `vence en ${horas} h` : `vence en ${minutos} min`;
  }

  pagarAhora(): void {
    if (!this.pendiente) return;
    this.router.navigate(['/proceso-de-pago/pago', this.pendiente.ped_numero]);
  }

  cerrar(): void {
    // Se oculta sólo para esta sesión de navegación; no es un "no mostrar
    // nunca más": el pedido sigue reservado y sigue valiendo la pena
    // recordárselo la próxima vez que entre.
    this.cerrado = true;
  }
}
import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { FiltroPedidos, PedidoListaItem } from '../../core/models/pedido-cliente.model';
import { PedidoClienteService } from '../../core/services/pedido-cliente.service';

/**
 * "Mis Pedidos".
 *
 * Es el destino al que se manda al cliente que cerró el navegador en
 * medio del checkout y perdió de vista el link de pago: la pestaña
 * "Pendientes de pago" junto con el botón "Pagar ahora" de cada fila es
 * lo que reemplaza a "volver al carrito" para un pedido ya confirmado.
 */
@Component({
  selector: 'app-mis-pedidos',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DatePipe, RouterModule],
  templateUrl: './mis-pedidos.component.html',
  styleUrl: './mis-pedidos.component.css',
})
export class MisPedidosComponent implements OnInit {
  /**
   * 'descripcion' aparece debajo de las pestañas, sólo la de la
   * seleccionada: "En proceso" en particular agrupa varias etapas
   * distintas (verificación, preparación, pago parcial) bajo un solo
   * nombre corto, y sin esa aclaración no queda claro qué incluye.
   */
  readonly pestanas: { valor: FiltroPedidos; etiqueta: string; descripcion: string }[] = [
    {
      valor: 'todos', etiqueta: 'Todos',
      descripcion: 'Todos tus pedidos, en cualquier estado.',
    },
    {
      valor: 'pendiente_pago', etiqueta: 'Pendientes de pago',
      descripcion: 'Pedidos que todavía no pagaste, o con un comprobante rechazado que podés corregir. '
        + 'Tenés 24 h antes de que la reserva se libere.',
    },
    {
      valor: 'en_proceso', etiqueta: 'En proceso',
      descripcion: 'Desde que declarás el pago hasta la entrega: pago en verificación, pago confirmado en preparación, o pago parcial con saldo contra entrega.',
    },
    {
      valor: 'entregados', etiqueta: 'Entregados',
      descripcion: 'Pedidos que ya recibiste.',
    },
    {
      valor: 'cancelados', etiqueta: 'Cancelados',
      descripcion: 'Pedidos cancelados, propios o por vencimiento del plazo de pago.',
    },
  ];

  get descripcionFiltro(): string {
    return this.pestanas.find((p) => p.valor === this.filtro)?.descripcion ?? '';
  }

  filtro: FiltroPedidos = 'todos';
  pedidos: PedidoListaItem[] = [];

  cargando = true;
  pagina = 1;
  ultimaPagina = 1;

  constructor(
    private servicio: PedidoClienteService,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  cambiarFiltro(filtro: FiltroPedidos): void {
    if (this.filtro === filtro) return;

    this.filtro = filtro;
    this.pagina = 1;
    this.cargar();
  }

  irAPagina(pagina: number): void {
    if (pagina < 1 || pagina > this.ultimaPagina || pagina === this.pagina) return;

    this.pagina = pagina;
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    this.servicio.listar(this.filtro, this.pagina).subscribe({
      next: (resp) => {
        this.pedidos = resp.data;
        this.ultimaPagina = resp.meta.last_page;
        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar tus pedidos.', 'Error');
      },
    });
  }

  // ---------- Presentación ----------

  /**
   * Clase del badge de estado del pedido. Vive en el componente y no en
   * el backend, siguiendo el mismo criterio que PedidoListaResource (el
   * admin): el Resource entrega el código, la pantalla decide el color.
   */
  claseEstadoPedido(codigo: string | null): string {
    return {
      PENDIENTE_PEDIDO: 'uk-label-warning',
      EN_PREPARACION: 'uk-label-warning',
      LISTO: 'uk-label-primary',
      ENTREGADO: 'uk-label-success',
      CANCELADO: 'uk-label-danger',
    }[codigo ?? ''] ?? 'uk-label';
  }

  claseEstadoPago(codigo: string | null): string {
    return {
      PENDIENTE_PAGO: 'uk-label-warning',
      PAGO_VERIFICAR: 'uk-label-primary',
      CONFIRMADO: 'uk-label-success',
      PARCIAL: 'uk-label-primary',
      RECHAZADO: 'uk-label-danger',
    }[codigo ?? ''] ?? 'uk-label';
  }

  restante(vence: string | null): string {
    if (!vence) return '';

    const faltan = new Date(vence).getTime() - Date.now();
    if (faltan <= 0) return 'vencido';

    const horas = Math.floor(faltan / 3_600_000);
    const minutos = Math.floor((faltan % 3_600_000) / 60_000);

    return horas > 0 ? `vence en ${horas} h` : `vence en ${minutos} min`;
  }

  /**
   * Mismo propósito que restante(), pero en días: el plazo de retiro se
   * mide en días hábiles, no en horas, así que "vence en 3 h" leería mal
   * para algo que en realidad dura varios días.
   *
   * vencidoRetiro() se usa aparte para decidir el color del texto: acá no
   * alcanza con "faltan <= 0" porque, a diferencia del plazo de pago, un
   * retiro vencido NO implica que el pedido se cancele — puede seguir
   * LISTO esperando que el admin lo resuelva, y el cliente igual necesita
   * ver "venció hace 2 días" en vez de que el texto desaparezca.
   */
  restanteRetiro(vence: string | null): string {
    if (!vence) return '';

    const dias = Math.round((new Date(vence).getTime() - Date.now()) / 86_400_000);

    if (dias > 0) return `vence en ${dias} día${dias === 1 ? '' : 's'}`;
    if (dias === 0) return 'vence hoy';

    const transcurridos = Math.abs(dias);
    return `venció hace ${transcurridos} día${transcurridos === 1 ? '' : 's'}`;
  }

  vencidoRetiro(vence: string | null): boolean {
    return !!vence && new Date(vence).getTime() < Date.now();
  }

  // ---------- Navegación ----------

  verDetalle(numero: string): void {
    this.router.navigate(['/mis-pedidos', numero]);
  }

  pagarAhora(evento: Event, numero: string): void {
    evento.stopPropagation();
    this.router.navigate(['/proceso-de-pago/pago', numero]);
  }
}
import { CommonModule, CurrencyPipe } from '@angular/common';
import {
  Component, ElementRef, Input, OnChanges, OnDestroy, OnInit, SimpleChanges,
} from '@angular/core';
import { Router } from '@angular/router';
import { URL_BACKEND } from '../../../config/config';
import { PedidoPendienteItem } from '../../../core/models/pedido-cliente.model';
import { PedidoClienteService } from '../../../core/services/pedido-cliente.service';

/**
 * Reemplaza al antiguo banner de cabecera. Ese banner tenía dos problemas
 * reales: se perdía al hacer scroll (sólo vivía en la parte de arriba de
 * la página) y mostraba un único pedido aunque hubiera varios pendientes.
 *
 * Esta pieza resuelve las dos cosas con tres señales que se alimentan de
 * la misma consulta:
 *   1. Un punto rojo permanente sobre el ícono de perfil, visible en
 *      cualquier página mientras existan pendientes (no sólo scrolleando
 *      hacia arriba).
 *   2. Una tarjeta flotante con la lista completa, que se abre sola sólo
 *      en la Home y se cierra a los 5 segundos.
 *   3. El contador que el header usa en el ítem "Mis pedidos" del
 *      dropdown de cuenta, vía la propiedad pública `cantidad`.
 *
 * El :host es position:absolute sobre el ícono de perfil (el header lo
 * coloca dentro de un contenedor position:relative que envuelve ese
 * ícono) con pointer-events:none, para no bloquear el clic sobre el
 * ícono salvo en el punto rojo y en la tarjeta abierta, que sí lo activan.
 */
@Component({
  selector: 'app-pedidos-pendientes-popover',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './pedidos-pendientes-popover.component.html',
  styleUrl: './pedidos-pendientes-popover.component.css',
})
export class PedidosPendientesPopoverComponent implements OnInit, OnChanges, OnDestroy {
  /** El header lo marca en true/false según haya sesión iniciada. */
  @Input() activo = false;

  /** El header lo marca en true sólo cuando la ruta actual es la Home. */
  @Input() enHome = false;

  pendientes: PedidoPendienteItem[] = [];
  abierto = false;

  /**
   * Posición vertical del panel, en px desde arriba del viewport. Se
   * calcula contra el borde inferior del ícono (position: fixed no se
   * puede anclar con CSS puro a un ancestro arbitrario), así el panel
   * siempre aparece justo debajo del header sin importar su alto.
   */
  panelTop = 0;

  private timerAutocierre?: ReturnType<typeof setTimeout>;

  get cantidad(): number {
    return this.pendientes.length;
  }

  constructor(
    private servicio: PedidoClienteService,
    private router: Router,
    private el: ElementRef<HTMLElement>,
  ) {}

  ngOnInit(): void {
    if (this.activo) this.consultar();
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['activo'] && !cambios['activo'].firstChange) {
      if (this.activo) {
        this.consultar();
      } else {
        this.pendientes = [];
        this.cerrar();
      }
    }
  }

  ngOnDestroy(): void {
    this.detenerTimer();
  }

  private consultar(): void {
    this.servicio.pendientes().subscribe({
      next: (resp) => {
        this.pendientes = resp.data;

        // Se abre sola sólo en la Home, y sólo si hay algo que avisar.
        if (this.enHome && this.pendientes.length > 0) {
          this.abrir();
        }
      },
      // El aviso es un recordatorio, no algo crítico: si la consulta
      // falla, no se interrumpe la navegación por eso.
      error: () => {},
    });
  }

  abrir(): void {
    // El panel es position:fixed respecto de la ventana, no del ícono
    // (así queda pegado al borde real del navegador y no al borde del
    // ícono, que no llega hasta ahí por el padding del header). Por eso
    // el "top" se calcula en JS contra el borde inferior del propio
    // :host, que sí está pegado al ícono.
    const rect = this.el.nativeElement.getBoundingClientRect();
    this.panelTop = rect.bottom + 10;

    this.abierto = true;
    this.detenerTimer();
    this.timerAutocierre = setTimeout(() => this.cerrar(), 5000);
  }

  cerrar(): void {
    this.abierto = false;
    this.detenerTimer();
  }

  private detenerTimer(): void {
    if (this.timerAutocierre) clearTimeout(this.timerAutocierre);
  }

  /** Mismo criterio que el resto de la tienda: la API devuelve rutas relativas. */
  resolverImagen(ruta: string | null | undefined): string | null {
    if (!ruta) return null;
    if (ruta.startsWith('http')) return ruta;
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
  }

  restante(vence: string): string {
    const faltan = new Date(vence).getTime() - Date.now();
    if (faltan <= 0) return 'vencido';

    const horas = Math.floor(faltan / 3_600_000);
    const minutos = Math.floor((faltan % 3_600_000) / 60_000);

    return horas > 0 ? `${horas} h ${minutos} min` : `${minutos} min`;
  }

  /**
   * Dos destinos posibles, según por qué el pedido sigue pendiente:
   *   - PENDIENTE_PAGO: nunca declaró su pago -> Paso 3 (pantalla de pago).
   *   - RECHAZADO: tiene un comprobante a corregir -> detalle del pedido,
   *     donde está el formulario de reemplazo. Mandarlo al Paso 3 en este
   *     caso devuelve 422 ("ya tiene un pago registrado").
   */
  accion(p: PedidoPendienteItem): void {
    this.cerrar();

    const destino = p.estado_pago === 'RECHAZADO'
      ? ['/mis-pedidos', p.ped_numero]
      : ['/proceso-de-pago/pago', p.ped_numero];

    this.router.navigate(destino);
  }

  etiquetaAccion(p: PedidoPendienteItem): string {
    return p.estado_pago === 'RECHAZADO' ? 'Corregir comprobante' : 'Pagar ahora';
  }
}
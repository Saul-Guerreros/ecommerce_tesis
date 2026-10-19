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

  /** Archivo elegido por pago (ppa_id -> File), antes de enviarlo. */
  archivos: Record<number, File | null> = {};
  /** ppa_id del pago que se está enviando, para bloquear sólo ese botón. */
  enviando: number | null = null;

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

  retiroVencido(): boolean {
    return !!this.pedido?.ped_vencret && new Date(this.pedido.ped_vencret).getTime() < Date.now();
  }

  // ---------- Comprobante rechazado ----------

  archivoElegido(evento: Event, pagoId: number): void {
    const input = evento.target as HTMLInputElement;
    this.archivos[pagoId] = input.files?.[0] ?? null;
  }

  /**
   * Envía el comprobante corregido de un pago rechazado.
   *
   * Tras el envío se recarga el detalle completo en vez de parchear el
   * objeto en memoria: el estado del pago, el del pedido y el plazo
   * cambian todos a la vez en el backend, y recalcularlos acá a mano
   * sería duplicar esa lógica en el frontend.
   */
  enviarComprobante(pagoId: number): void {
    const archivo = this.archivos[pagoId];

    if (!archivo) {
      this.toastr.warning('Elegí el comprobante corregido.', 'Falta el archivo');
      return;
    }

    this.enviando = pagoId;

    this.servicio.reemplazarComprobante(this.numero, pagoId, archivo).subscribe({
      next: (resp) => {
        this.toastr.success(resp.message, 'Listo');
        this.archivos[pagoId] = null;
        this.enviando = null;
        this.cargar();
      },
      error: (e) => {
        this.enviando = null;
        this.toastr.error(this.mensajeError(e), 'Error');
      },
    });
  }

  /**
   * El 422 de Laravel trae los errores por campo; el mensaje suelto sólo
   * aparece en los abort(). Se prioriza el del campo porque es el que
   * dice qué corregir ("El comprobante no puede superar los 5 MB").
   */
  private mensajeError(e: any): string {
    const porCampo = e?.error?.errors ? Object.values(e.error.errors)[0] : null;

    if (Array.isArray(porCampo) && porCampo.length) {
      return porCampo[0] as string;
    }

    return e?.error?.message ?? 'No pudimos enviar el comprobante.';
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
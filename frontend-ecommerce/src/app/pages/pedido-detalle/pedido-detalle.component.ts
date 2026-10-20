import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../config/config';
import { PagoDetalle, PedidoDetalle } from '../../core/models/pedido-cliente.model';
import { PedidoClienteService } from '../../core/services/pedido-cliente.service';
import { DatosEntregaCardComponent } from '../../shared/components/datos-entrega-card/datos-entrega-card.component';
import { DatosFacturacionCardComponent } from '../../shared/components/datos-facturacion-card/datos-facturacion-card.component';
import { ProductosCarritoCardComponent } from '../../shared/components/productos-carrito-card/productos-carrito-card.component';

/** Mismo límite y formatos que el comprobante del Paso 3 del checkout. */
const TIPOS_COMPROBANTE = ['image/jpeg', 'image/png', 'application/pdf'];
const MAX_COMPROBANTE_BYTES = 5 * 1024 * 1024;

/**
 * Detalle de un pedido ya existente (ruta /mis-pedidos/:numero).
 *
 * Es de sólo lectura salvo por dos acciones que decide el backend:
 *  - "Pagar ahora" (pedido.pagable)
 *  - subir un comprobante nuevo en un pago rechazado (pago.reemplazable)
 *
 * La pantalla no deduce ninguna de las dos: sólo obedece los flags.
 */
@Component({
  selector: 'app-pedido-detalle',
  standalone: true,
  imports: [
    CommonModule,
    CurrencyPipe,
    DatePipe,
    RouterModule,
    ProductosCarritoCardComponent,
    DatosEntregaCardComponent,
    DatosFacturacionCardComponent,
  ],
  templateUrl: './pedido-detalle.component.html',
  styleUrl: './pedido-detalle.component.css',
})
export class PedidoDetalleComponent implements OnInit {
  numero = '';
  pedido: PedidoDetalle | null = null;
  cargando = true;

  /** Comprobante nuevo elegido por pago (clave: ppa_id). */
  archivos: Record<number, File | null> = {};
  /** Mensaje de validación del archivo por pago. */
  erroresArchivo: Record<number, string> = {};
  /** ppa_id del pago que se está subiendo (bloquea su botón). */
  subiendoId: number | null = null;
  /** ppa_id del pago cuyo comprobante se está abriendo. */
  abriendoId: number | null = null;

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

  get esRetiro(): boolean {
    return this.pedido?.entrega?.modalidad === 'retiro';
  }

  // ---------- Presentación (mismos criterios que Mis Pedidos) ----------

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

  // ---------- Acciones ----------

  pagarAhora(): void {
    this.router.navigate(['/proceso-de-pago/pago', this.numero]);
  }

  /**
   * Abre el comprobante en una pestaña nueva PASANDO por HttpClient (que
   * adjunta el token); un enlace directo sería rechazado por el backend.
   * Ver PedidoClienteService.descargarArchivo().
   */
  verComprobante(pago: PagoDetalle): void {
    if (!pago.comprobante_url || this.abriendoId !== null) return;

    const url = pago.comprobante_url.startsWith('http')
      ? pago.comprobante_url
      : URL_BACKEND.replace(/\/$/, '') + '/' + pago.comprobante_url.replace(/^\//, '');

    this.abriendoId = pago.ppa_id;

    this.servicio.descargarArchivo(url).subscribe({
      next: (blob) => {
        const objeto = URL.createObjectURL(blob);
        window.open(objeto, '_blank');
        // Se libera más tarde: revocarlo ya mismo puede cortar la carga de la pestaña.
        setTimeout(() => URL.revokeObjectURL(objeto), 60_000);
        this.abriendoId = null;
      },
      error: () => {
        this.abriendoId = null;
        this.toastr.error('No pudimos abrir el comprobante.', 'Error');
      },
    });
  }

  onArchivoSeleccionado(evento: Event, pago: PagoDetalle): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;

    delete this.erroresArchivo[pago.ppa_id];

    if (archivo && !TIPOS_COMPROBANTE.includes(archivo.type)) {
      this.erroresArchivo[pago.ppa_id] = 'El comprobante debe ser JPG, PNG o PDF.';
      input.value = '';
      this.archivos[pago.ppa_id] = null;
      return;
    }

    if (archivo && archivo.size > MAX_COMPROBANTE_BYTES) {
      this.erroresArchivo[pago.ppa_id] = 'El comprobante no puede superar los 5 MB.';
      input.value = '';
      this.archivos[pago.ppa_id] = null;
      return;
    }

    this.archivos[pago.ppa_id] = archivo;
  }

  enviarComprobante(pago: PagoDetalle): void {
    const archivo = this.archivos[pago.ppa_id];

    if (!archivo) {
      this.erroresArchivo[pago.ppa_id] = 'Adjuntá el comprobante de pago.';
      return;
    }
    if (this.subiendoId !== null) return;

    this.subiendoId = pago.ppa_id;

    this.servicio.reemplazarComprobante(this.numero, pago.ppa_id, archivo).subscribe({
      next: (resp) => {
        this.subiendoId = null;
        delete this.archivos[pago.ppa_id];
        this.toastr.success(resp.message ?? 'Comprobante enviado.', 'Listo');
        this.cargar();
      },
      error: (e) => {
        this.subiendoId = null;
        this.toastr.error(
          e.error?.errors?.comprobante?.[0] ?? e.error?.message ?? 'No pudimos enviar el comprobante.',
          'Error',
        );
      },
    });
  }
}

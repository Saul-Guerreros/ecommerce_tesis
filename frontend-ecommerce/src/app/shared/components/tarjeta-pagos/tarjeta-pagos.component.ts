import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';

/** Forma mínima de un pago para la tarjeta (sirve para pedido y servicio). */
export interface PagoTarjeta {
  id: number;
  orden: number;
  metodo: string | null;
  monto: number;
  estado: string | null;
  estado_descri: string | null;
  tiene_comprobante: boolean;
  comprobante_url: string | null;
  motivo_rechazo: { motivo: string | null; detalle: string | null } | null;
  reemplazable: boolean;
}

const TIPOS_COMPROBANTE = ['image/jpeg', 'image/png', 'application/pdf'];
const MAX_COMPROBANTE_BYTES = 5 * 1024 * 1024;

/**
 * Tarjeta "Pagos" presentacional: lista los pagos, abre el comprobante y
 * permite subir uno nuevo en un pago rechazado. No llama al backend: emite
 * (verComprobante) y (reemplazar) y el contenedor hace la petición.
 *
 * Replica la tarjeta de pedido-detalle (mismas clases de estado y mismo
 * flujo de rechazo) sin modificarla, para no tocar el pedido en esta etapa.
 */
@Component({
  selector: 'app-tarjeta-pagos',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './tarjeta-pagos.component.html',
  styleUrl: './tarjeta-pagos.component.css',
})
export class TarjetaPagosComponent {
  @Input() pagos: PagoTarjeta[] = [];
  @Input() vacio = 'Todavía no registraste ningún pago.';

  /** Ids que el contenedor está procesando (contra el doble clic). */
  @Input() abriendoId: number | null = null;
  @Input() subiendoId: number | null = null;

  @Output() verComprobante = new EventEmitter<PagoTarjeta>();
  @Output() reemplazar = new EventEmitter<{ pago: PagoTarjeta; archivo: File }>();

  archivos: Record<number, File | null> = {};
  errores: Record<number, string | null> = {};

  claseEstado(codigo: string | null): string {
    return {
      PENDIENTE_PAGO: 'uk-label-warning',
      PAGO_VERIFICAR: 'uk-label-primary',
      CONFIRMADO: 'uk-label-success',
      PARCIAL: 'uk-label-primary',
      RECHAZADO: 'uk-label-danger',
    }[codigo ?? ''] ?? 'uk-label';
  }

  onArchivo(evento: Event, pago: PagoTarjeta): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;

    this.errores[pago.id] = null;
    this.archivos[pago.id] = null;

    if (archivo && !TIPOS_COMPROBANTE.includes(archivo.type)) {
      this.errores[pago.id] = 'El comprobante debe ser JPG, PNG o PDF.';
      input.value = '';
      return;
    }

    if (archivo && archivo.size > MAX_COMPROBANTE_BYTES) {
      this.errores[pago.id] = 'El comprobante no puede superar los 5 MB.';
      input.value = '';
      return;
    }

    this.archivos[pago.id] = archivo;
  }

  enviar(pago: PagoTarjeta): void {
    const archivo = this.archivos[pago.id];

    if (!archivo) {
      this.errores[pago.id] = 'Adjuntá el comprobante de pago.';
      return;
    }
    if (this.subiendoId !== null) return;

    this.reemplazar.emit({ pago, archivo });
  }

  /** El contenedor lo llama cuando el reemplazo terminó bien. */
  limpiar(id: number): void {
    delete this.archivos[id];
    delete this.errores[id];
  }
}

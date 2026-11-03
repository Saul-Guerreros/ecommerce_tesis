import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BadgeModule, ButtonModule, FormModule, ModalModule, TableModule } from '@coreui/angular';
import {
  DetNotaCreditoVenta, NotaCreditoVenta, NotaCreditoVentaFe,
} from '../models/nota-credito-venta.model';

/**
 * Vista de solo lectura. No hay modo edición y no es un descuido: una NC
 * emitida es un comprobante fiscal inmutable. Si está mal, se anula.
 *
 * El padre es quien llama a la API (KuDE, XML, reintento): acá solo se
 * emiten los eventos y se refleja el estado.
 */
@Component({
  selector: 'app-ver-nota-credito-venta',
  standalone: true,
  imports: [CommonModule, ModalModule, ButtonModule, TableModule, BadgeModule, FormModule],
  templateUrl: './ver-nota-credito-venta.component.html',
})
export class VerNotaCreditoVentaComponent {
  @Input() visible = false;
  @Input() nota: NotaCreditoVenta | null = null;
  /** Hay un reintento de la declaración electrónica en curso. */
  @Input() reintentando = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() anular = new EventEmitter<NotaCreditoVenta>();
  @Output() descargarKude = new EventEmitter<NotaCreditoVenta>();
  @Output() descargarXml = new EventEmitter<NotaCreditoVenta>();
  @Output() reintentarFe = new EventEmitter<NotaCreditoVenta>();

  get detalle(): DetNotaCreditoVenta[] {
    return this.nota?.detalle ?? [];
  }

  get esAnulada(): boolean {
    return this.nota?.ncv_estado === 9;
  }

  get identificacion(): string {
    if (!this.nota) return '';
    return this.nota.ncv_ruc ? `RUC ${this.nota.ncv_ruc}` : `CI ${this.nota.ncv_ci}`;
  }

  // ---------- Nota de crédito electrónica ----------

  get fe(): NotaCreditoVentaFe | null {
    return this.nota?.fe ?? null;
  }

  get feColor(): string {
    if (this.fe?.estado === 2) return 'success';
    if (this.fe?.estado === 3) return 'danger';
    return 'warning';
  }

  get feTexto(): string {
    if (this.fe?.estado === 2) return 'Enviada';
    if (this.fe?.estado === 3) return 'Rechazada';
    return 'Pendiente';
  }

  get esPrueba(): boolean {
    return this.fe?.ambiente === 'simulado';
  }

  get puedeReintentar(): boolean {
    return !this.esAnulada && this.fe?.estado === 1;
  }

  /** Una NC declarada (o en proceso) no se anula en el sistema: la DNIT la tiene. */
  get puedeAnular(): boolean {
    return !this.esAnulada && !this.fe?.bloquea_anulacion;
  }

  /** Columna Exentas / 5% / 10% del renglón impreso, igual que en la factura. */
  columna(item: DetNotaCreditoVenta, tasa: number): number | null {
    return Number(item.dnv_tasa) === tasa ? Number(item.dnv_valorventa) : null;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  pedirAnular(): void {
    if (this.nota) this.anular.emit(this.nota);
  }

  pedirKude(): void {
    if (this.nota) this.descargarKude.emit(this.nota);
  }

  pedirXml(): void {
    if (this.nota) this.descargarXml.emit(this.nota);
  }

  pedirReintento(): void {
    if (this.nota) this.reintentarFe.emit(this.nota);
  }
}
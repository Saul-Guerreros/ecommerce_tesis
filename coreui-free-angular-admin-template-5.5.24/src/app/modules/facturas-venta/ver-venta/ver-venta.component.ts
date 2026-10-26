import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BadgeModule, ButtonModule, FormModule, ModalModule, TableModule } from '@coreui/angular';
import { Venta, VentaDetalle, VentaFe } from '../models/venta.model';

/**
 * Vista de solo lectura de una factura emitida, con el mismo esquema que
 * el detalle de Facturas de Compra.
 *
 * No hay modo edicion y no es un descuido: un comprobante fiscal emitido
 * es inmutable. Si algo esta mal, se anula y se emite otro.
 */
@Component({
  selector: 'app-ver-venta',
  standalone: true,
  imports: [CommonModule, FormsModule, ModalModule, ButtonModule, TableModule, BadgeModule, FormModule],
  templateUrl: './ver-venta.component.html',
})
export class VerVentaComponent {
  @Input() visible = false;
  @Input() venta: Venta | null = null;
  /** El padre es quien llama a la API: aca solo se refleja que hay un reintento en curso. */
  @Input() reintentando = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() anular = new EventEmitter<Venta>();
  @Output() descargarKude = new EventEmitter<Venta>();
  @Output() descargarXml = new EventEmitter<Venta>();
  @Output() reintentarFe = new EventEmitter<Venta>();

  get detalle(): VentaDetalle[] {
    return this.venta?.detalle ?? [];
  }

  get esAnulada(): boolean {
    return this.venta?.ven_estado === 9;
  }

  get fe(): VentaFe | null {
    return this.venta?.fe ?? null;
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

  /** Una factura aprobada (o en proceso) ante la DNIT no se anula: se corrige con una nota de credito. */
  get puedeAnular(): boolean {
    return !this.esAnulada && !this.fe?.bloquea_anulacion;
  }

  get condicion(): string {
    return this.venta?.ven_condicion === 2 ? 'Crédito' : 'Contado';
  }

  get identificacion(): string {
    if (!this.venta) return '';
    return this.venta.ven_ruc ? `RUC ${this.venta.ven_ruc}` : `CI ${this.venta.ven_ci}`;
  }

  /**
   * Columna Exentas / 5% / 10% del renglon impreso. El valor de la linea
   * cae en una sola de las tres, segun su tasa.
   */
  columna(item: VentaDetalle, tasa: number): number | null {
    return Number(item.vnd_tasa) === tasa ? Number(item.vnd_valorventa) : null;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  pedirAnular(): void {
    if (this.venta) {
      this.anular.emit(this.venta);
    }
  }

  pedirKude(): void {
    if (this.venta) {
      this.descargarKude.emit(this.venta);
    }
  }

  pedirXml(): void {
    if (this.venta) {
      this.descargarXml.emit(this.venta);
    }
  }

  pedirReintento(): void {
    if (this.venta) {
      this.reintentarFe.emit(this.venta);
    }
  }
}
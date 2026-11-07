import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Validar el comprobante de un pago web (igual que pedidos): solo marca el
 * comprobante como revisado. NO cobra: el cobro se registra después,
 * contra la factura, con "Cobrar".
 */
@Component({
  selector: 'app-validar-comprobante-servicio',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './validar-comprobante-servicio.component.html',
})
export class ValidarComprobanteServicioComponent implements OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() pago: PagoServicio | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();
  /** El admin decide rechazar desde este modal: lo abre el contenedor. */
  @Output() rechazar = new EventEmitter<PagoServicio>();

  confirma = false;
  observacion = '';
  error: string | null = null;
  guardando = false;
  abriendo = false;

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.confirma = false;
      this.observacion = '';
      this.error = null;
    }
  }

  verComprobante(): void {
    if (!this.detalle || !this.pago || this.abriendo) return;
    this.abriendo = true;
    this.service.comprobante(this.detalle.sse_id, this.pago.spa_id).subscribe({
      next: (blob) => { this.abriendo = false; this.service.abrirBlob(blob); },
      error: () => { this.abriendo = false; this.toastr.error('No se pudo abrir el comprobante', 'Error'); },
    });
  }

  validar(): void {
    if (!this.detalle || !this.pago || this.guardando) return;
    if (!this.confirma) { this.error = 'Confirmá que el comprobante cubre el monto asignado a este pago.'; return; }

    this.guardando = true;
    this.service.validarComprobante(this.detalle.sse_id, this.pago.spa_id, {
      confirma_monto: true,
      observacion: this.observacion.trim() || null,
    }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Comprobante validado');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        this.error = CobrosServicioService.mensajeError(err, 'No se pudo validar el comprobante');
      },
    });
  }

  pedirRechazo(): void {
    const pago = this.pago;
    this.cerrar();
    if (pago) this.rechazar.emit(pago);
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
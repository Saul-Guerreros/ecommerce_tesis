import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule,
} from '@coreui/angular';

import { PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';

/**
 * Modal «Validar comprobante».
 *
 * Primer paso del ciclo. El administrador abre el comprobante, lo compara
 * con el extracto del banco y confirma que cubre el monto asignado a ese
 * pago. No mueve dinero ni stock: sólo habilita el cobro.
 *
 * La casilla de confirmación del monto NO es un trámite. Ese monto es el
 * que después se declara a la DNIT, y una factura declarada sólo se
 * corrige con nota de crédito. Por eso es obligatoria y el botón queda
 * deshabilitado hasta tildarla.
 */
@Component({
  selector: 'app-validar-comprobante',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, ModalModule, ButtonModule,
    FormModule, AlertModule, SpinnerModule,
  ],
  templateUrl: './validar-comprobante.component.html',
})
export class ValidarComprobanteComponent implements OnChanges {
  @Input() visible = false;
  @Input() pedido: PedidoDetalle | null = null;
  @Input() pago: PagoItem | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<PedidoDetalle>();

  /** El rechazo se decide acá: es el momento en que se mira el comprobante. */
  @Output() rechazar = new EventEmitter<PagoItem>();

  form!: FormGroup;
  guardando = false;
  descargando = false;

  constructor(
    private fb: FormBuilder,
    private service: PedidosService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      confirma_monto: [false, [Validators.requiredTrue]],
      observacion: [''],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({ confirma_monto: false, observacion: '' });
    }
  }

  get montoAsignado(): number {
    return this.pago?.monto_asignado ?? 0;
  }

  get puedeGuardar(): boolean {
    return this.form.valid && !this.guardando;
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

    /**
   * Ver y descargar comparten la misma descarga por blob: la ruta del
   * comprobante exige token JWT, así que un <a href> directo daría 401.
   */
  verComprobante(): void {
    this.obtenerComprobante((blobUrl) => {
      window.open(blobUrl, '_blank');
      setTimeout(() => window.URL.revokeObjectURL(blobUrl), 60000);
    });
  }

  descargarComprobante(): void {
    this.obtenerComprobante((blobUrl) => {
      const enlace = document.createElement('a');
      enlace.href = blobUrl;
      enlace.download = this.pago?.comprobante?.archivo ?? 'comprobante';
      enlace.click();
      window.URL.revokeObjectURL(blobUrl);
    });
  }

  private obtenerComprobante(accion: (blobUrl: string) => void): void {
    if (!this.pedido || !this.pago || this.descargando) return;

    this.descargando = true;

    this.service.comprobante(this.pedido.ped_id, this.pago.ppa_id).subscribe({
      next: (blob) => {
        accion(window.URL.createObjectURL(blob));
        this.descargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo obtener el comprobante', 'Error');
        this.descargando = false;
      },
    });
  }

  validar(): void {
    if (!this.pedido || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    this.service
      .validarComprobante(this.pedido.ped_id, this.pago.ppa_id, {
        confirma_monto: true,
        observacion: this.form.value.observacion || null,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Comprobante validado');
          this.guardado.emit(res.data);
          this.guardando = false;
          this.cerrar();
        },
        error: (err) => {
          this.guardando = false;

          const errores = err?.error?.errors;
          if (errores) {
            Object.entries(errores).forEach(([campo, mensajes]) => {
              this.form.get(campo)?.setErrors({ backend: (mensajes as string[])[0] });
            });
            this.toastr.error(
              (Object.values(errores)[0] as string[])[0],
              'No se pudo validar',
            );
            return;
          }

          this.toastr.error(err?.error?.message ?? 'No se pudo validar el comprobante', 'Error');
        },
      });
  }

  /** Cierra este modal y pide al contenedor que abra el de rechazo. */
  pedirRechazo(): void {
    if (!this.pago) return;
    const pago = this.pago;
    this.cerrar();
    this.rechazar.emit(pago);
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
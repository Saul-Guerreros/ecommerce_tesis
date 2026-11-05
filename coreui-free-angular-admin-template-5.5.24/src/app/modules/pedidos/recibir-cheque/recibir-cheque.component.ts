import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule,
} from '@coreui/angular';

import { PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';

/**
 * Modal «Confirmar recepción de cheque».
 *
 * Es el equivalente de Validar comprobante, pero no es simétrico: validar
 * un comprobante sólo habilita el cobro, mientras que confirmar una
 * recepción puede descontar stock y dejar el pedido listo para entregar.
 * Por eso la pantalla avisa explícitamente qué va a pasar con el
 * inventario, según la política configurada en Métodos de Pago.
 *
 * El monto NO se edita ni viaja en el payload: sale de pedido_pago. La
 * casilla sólo declara que el papel está emitido por ese importe, que es
 * el que después se factura.
 */
@Component({
  selector: 'app-recibir-cheque',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, ModalModule, ButtonModule,
    FormModule, AlertModule, SpinnerModule, BadgeModule,
  ],
  templateUrl: './recibir-cheque.component.html',
})
export class RecibirChequeComponent implements OnChanges {
  @Input() visible = false;
  @Input() pedido: PedidoDetalle | null = null;
  @Input() pago: PagoItem | null = null;

  /** Viene de /gestion/config: define el aviso sobre el stock. */
  @Input() stockAlRecibir = true;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<PedidoDetalle>();

  /** El rechazo se decide acá: es el momento en que se mira el papel. */
  @Output() rechazar = new EventEmitter<PagoItem>();

  form!: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: PedidosService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      // Bloqueado: la fecha real de recepción la fija el servidor al confirmar.
      // Se muestra solo como referencia, por eso no necesita validadores.
      fecha_recepcion: [{ value: '', disabled: true }],
      confirma_monto: [false, [Validators.requiredTrue]],
      observacion: [''],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({
        fecha_recepcion: this.ahoraLocal(),
        confirma_monto: false,
        observacion: '',
      });
    }
  }

  /**
   * Fecha y hora actuales en el formato que pide <input type="datetime-local">.
   * toISOString() no sirve: devuelve UTC y en Paraguay adelantaría la hora
   * de recepción tres horas.
   */
  private ahoraLocal(): string {
    const ahora = new Date();
    const desfase = ahora.getTimezoneOffset() * 60000;

    return new Date(ahora.getTime() - desfase).toISOString().slice(0, 16);
  }

  get cheque() {
    return this.pago?.cheque ?? null;
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

  confirmar(): void {
    if (!this.pedido || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    this.service
      .recibirCheque(this.pedido.ped_id, this.pago.ppa_id, {
                confirma_monto: true,
        // La fecha de recepción no se envía: la registra el servidor.
        observacion: this.form.value.observacion || null,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Cheque recibido');
          this.guardado.emit(res.data);
          this.guardando = false;
          this.cerrar();
        },
        error: (err) => {
          this.guardando = false;

          // 409: no alcanzó el stock al intentar descontarlo.
          const faltantes = err?.error?.faltantes;
          if (err?.status === 409 && faltantes?.length) {
            this.toastr.error(
              `Sin stock: ${faltantes[0].producto}`,
              'No se pudo confirmar la recepción',
            );
            return;
          }

          const errores = err?.error?.errors;
          if (errores) {
            Object.entries(errores).forEach(([campo, mensajes]) => {
              this.form.get(campo)?.setErrors({ backend: (mensajes as string[])[0] });
            });
            this.toastr.error(
              (Object.values(errores)[0] as string[])[0],
              'No se pudo confirmar la recepción',
            );
            return;
          }

          this.toastr.error(err?.error?.message ?? 'No se pudo confirmar la recepción', 'Error');
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
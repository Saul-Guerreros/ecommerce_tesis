import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule,
} from '@coreui/angular';

import { FaltanteStock, PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';

/**
 * Modal «Confirmar pago».
 *
 * Toda la carga de datos de la verificación ocurre acá: la tarjeta del
 * pago sólo muestra el resultado. Por eso el formulario arranca vacío
 * salvo el monto, que se precarga con el monto asignado (el caso normal
 * es que coincida).
 *
 * La validación del monto es asimétrica a propósito:
 *   - Monto MENOR al asignado  → bloqueado. Confirmarlo dejaría el pedido
 *     como pagado cuando el cliente todavía debe la diferencia; lo que
 *     corresponde es rechazar el pago.
 *   - Monto MAYOR al asignado  → permitido con confirmación explícita.
 *     Un excedente puede ser legítimo (transferencia de más, redondeo).
 *
 * El aviso se evalúa en (blur) y no en cada tecla: escribiendo "100000"
 * el campo pasa por 1, 10, 100... y mostrar la alerta en cada paso es
 * ruido que entrena al usuario a ignorarla.
 */
@Component({
  selector: 'app-confirmar-pago',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, ModalModule, ButtonModule,
    FormModule, AlertModule, SpinnerModule,
  ],
  templateUrl: './confirmar-pago.component.html',
})
export class ConfirmarPagoComponent implements OnChanges {
  @Input() visible = false;
  @Input() pedido: PedidoDetalle | null = null;
  @Input() pago: PagoItem | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<PedidoDetalle>();

  /** El modal de rechazo se abre desde acá, como indica el flujo: el
   *  administrador entra a validar y recién ahí decide si rechaza. */
  @Output() rechazar = new EventEmitter<PagoItem>();

  form!: FormGroup;
  guardando = false;

  /** Recién cuando el admin sale del campo se evalúa la diferencia. */
  montoTocado = false;

  /** Lista de productos sin stock devuelta por el 409. */
  faltantes: FaltanteStock[] = [];

  constructor(
    private fb: FormBuilder,
    private service: PedidosService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      monto_verificado: [null as number | null, [Validators.required, Validators.min(1)]],
      nro_transaccion: [''],
      banco: [''],
      fecha_pago: [''],
      observacion: [''],
      confirmar_diferencia: [false],
    });
  }

  /** Resetea el formulario cada vez que se abre con un pago distinto. */
  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.faltantes = [];
      this.montoTocado = false;

      this.form.reset({
        monto_verificado: this.pago.monto_asignado,
        nro_transaccion: '',
        banco: this.pago.metodo.descri ?? '',
        fecha_pago: new Date().toISOString().slice(0, 10),
        observacion: '',
        confirmar_diferencia: false,
      });
    }
  }

  // ---------- Montos ----------

  get montoEsperado(): number {
    return this.pago?.monto_asignado ?? 0;
  }

  get montoVerificado(): number {
    return Number(this.form.get('monto_verificado')?.value ?? 0);
  }

  /** Positiva si pagó de más, negativa si pagó de menos. */
  get diferencia(): number {
    return this.montoVerificado - this.montoEsperado;
  }

  /** Valor absoluto, para mostrarlo sin signo en los mensajes. */
  get faltante(): number {
    return Math.abs(this.diferencia);
  }

  /** Pago incompleto: no se puede confirmar, corresponde rechazar. */
  get montoInsuficiente(): boolean {
    return this.montoTocado && this.montoVerificado > 0 && this.diferencia <= -1;
  }

  /** Pago en exceso: puede ser legítimo, requiere confirmación. */
  get montoExcedente(): boolean {
    return this.montoTocado && this.diferencia >= 1;
  }

  onMontoBlur(): void {
    this.montoTocado = true;
  }

  // ---------- Estado derivado ----------

  /**
   * Si este es el último pago pendiente, confirmarlo dispara el descuento
   * de stock y el avance del pedido. El aviso del modal se muestra sólo
   * en ese caso para que no pierda fuerza por repetirse.
   */
  get quedaraTotalmentePagado(): boolean {
    if (!this.pedido || !this.pago) return false;

    const otrosPendientes = this.pedido.pago.items.filter(
      (p) => p.ppa_id !== this.pago!.ppa_id && p.estado.codigo !== 'CONFIRMADO',
    );

    return otrosPendientes.length === 0;
  }

  get puedeGuardar(): boolean {
    if (this.form.invalid || this.guardando) return false;
    if (this.montoInsuficiente) return false;
    if (this.montoExcedente && !this.form.get('confirmar_diferencia')?.value) return false;
    return true;
  }

  campoInvalido(campo: string): boolean {
    const control = this.form.get(campo);
    return !!control && control.invalid && (control.dirty || control.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  // ---------- Acciones ----------

  confirmar(): void {
    if (!this.pedido || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.faltantes = [];

    const valor = this.form.value;

    this.service
      .confirmarPago(this.pedido.ped_id, this.pago.ppa_id, {
        monto_verificado: Number(valor.monto_verificado),
        nro_transaccion: valor.nro_transaccion || null,
        banco: valor.banco || null,
        fecha_pago: valor.fecha_pago || null,
        observacion: valor.observacion || null,
        confirmar_diferencia: !!valor.confirmar_diferencia,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Pago confirmado');
          this.guardado.emit(res.data);
          this.guardando = false;
          this.cerrar();
        },
        error: (err) => {
          this.guardando = false;

          // 409: el stock reservado ya no está disponible. El modal no se
          // cierra: muestra el bloqueo con la lista de productos.
          if (err?.status === 409 && err?.error?.faltantes) {
            this.faltantes = err.error.faltantes;
            return;
          }

          // 422: errores de validación junto al campo (estándar del proyecto).
          const errores = err?.error?.errors;
          if (errores) {
            Object.entries(errores).forEach(([campo, mensajes]) => {
              this.form.get(campo)?.setErrors({ backend: (mensajes as string[])[0] });
            });
            this.toastr.error(
              (Object.values(errores)[0] as string[])[0],
              'No se pudo confirmar',
            );
            return;
          }

          this.toastr.error(err?.error?.message ?? 'No se pudo confirmar el pago', 'Error');
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
    this.faltantes = [];
    this.montoTocado = false;
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule,
} from '@coreui/angular';

import { MotivoRechazo, PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';

/**
 * Modal «Rechazar pago».
 *
 * El detalle que se escriba acá lo verá el cliente en su sección
 * «Mis pedidos», así que el texto tiene que explicar qué corregir.
 * Cuando el motivo es por monto, la diferencia faltante se calcula sola
 * (esperado − verificado) y se incorpora al mensaje.
 */
@Component({
  selector: 'app-rechazar-pago',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, ModalModule, ButtonModule,
    FormModule, AlertModule, SpinnerModule,
  ],
  templateUrl: './rechazar-pago.component.html',
})
export class RechazarPagoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() pedido: PedidoDetalle | null = null;
  @Input() pago: PagoItem | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<PedidoDetalle>();

  form!: FormGroup;
  motivos: MotivoRechazo[] = [];
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: PedidosService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      motivo_id: [null as number | null, Validators.required],
      monto_verificado: [null as number | null],
      detalle: [''],
    });
  }

  ngOnInit(): void {
    this.service.configGestion().subscribe({
      next: (res) => (this.motivos = res.motivos_rechazo),
      error: () => this.toastr.error('No se pudieron cargar los motivos', 'Error'),
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({ motivo_id: null, monto_verificado: null, detalle: '' });
    }
  }

  // ---------- Estado derivado ----------

  get motivoSeleccionado(): MotivoRechazo | undefined {
    const id = this.form.get('motivo_id')?.value;
    return this.motivos.find((m) => m.mrp_id === id);
  }

  /** Sólo el motivo por monto necesita que se cargue el importe real. */
  get pideMonto(): boolean {
    return this.motivoSeleccionado?.mrp_codigo === 'MONTO_NO_COINCIDE';
  }

  get detalleObligatorio(): boolean {
    return !!this.motivoSeleccionado?.mrp_requdet;
  }

  get montoEsperado(): number {
    return this.pago?.monto_asignado ?? 0;
  }

  get montoVerificado(): number {
    return Number(this.form.get('monto_verificado')?.value ?? 0);
  }

  /** Esperado − verificado. Es lo que el cliente todavía debe cubrir. */
  get faltante(): number {
    const diferencia = this.montoEsperado - this.montoVerificado;
    return diferencia > 0 ? diferencia : 0;
  }

  get puedeGuardar(): boolean {
    if (this.form.invalid || this.guardando) return false;
    if (this.detalleObligatorio && !String(this.form.get('detalle')?.value ?? '').trim()) return false;
    if (this.pideMonto && this.montoVerificado <= 0) return false;
    return true;
  }

  /** Sugerencia de texto para el cliente según el motivo elegido. */
  onMotivoChange(): void {
    const control = this.form.get('detalle');

    if (!this.pideMonto) {
      this.form.patchValue({ monto_verificado: null });
    }

    if (!control?.value && this.motivoSeleccionado) {
      control?.setValue(this.motivoSeleccionado.mrp_descri);
    }
  }

  onMontoChange(): void {
    if (!this.pideMonto || this.montoVerificado <= 0) return;

    this.form.patchValue({
      detalle:
        `El comprobante corresponde a Gs. ${this.formatoGs(this.montoVerificado)}. ` +
        `El monto declarado es Gs. ${this.formatoGs(this.montoEsperado)}. ` +
        `Diferencia faltante: Gs. ${this.formatoGs(this.faltante)}.`,
    });
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  // ---------- Acciones ----------

  rechazar(): void {
    if (!this.pedido || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    const valor = this.form.value;

    this.service
      .rechazarPago(this.pedido.ped_id, this.pago.ppa_id, {
        motivo_id: Number(valor.motivo_id),
        detalle: valor.detalle || null,
        monto_verificado: this.pideMonto ? Number(valor.monto_verificado) : null,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Pago rechazado');
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
            this.toastr.error((Object.values(errores)[0] as string[])[0], 'No se pudo rechazar');
            return;
          }

          this.toastr.error(err?.error?.message ?? 'No se pudo rechazar el pago', 'Error');
        },
      });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
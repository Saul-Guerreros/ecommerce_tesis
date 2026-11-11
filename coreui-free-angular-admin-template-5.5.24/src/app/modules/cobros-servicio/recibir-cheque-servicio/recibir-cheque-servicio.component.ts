import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Modal «Confirmar recepción de cheque» del servicio. Espejo de
 * recibir-cheque (pedidos): el monto no se edita y la fecha la fija el
 * servidor. En servicio no hay stock: la recepción habilita la factura y,
 * según la política de Métodos de Pago, la entrega del equipo.
 */
@Component({
  selector: 'app-recibir-cheque-servicio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule],
  templateUrl: './recibir-cheque-servicio.component.html',
})
export class RecibirChequeServicioComponent implements OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() pago: PagoServicio | null = null;

  /** ctn_cheque_stock = RECEPCION (viene de /config). */
  @Input() entregaAlRecibir = true;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();
  @Output() rechazar = new EventEmitter<PagoServicio>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      fecha_recepcion: [{ value: '', disabled: true }],
      confirma_monto: [false, [Validators.requiredTrue]],
      observacion: ['', [Validators.maxLength(300)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({ fecha_recepcion: this.ahoraLocal(), confirma_monto: false, observacion: '' });
    }
  }

  /** toISOString() devuelve UTC: en Paraguay adelantaría la hora. */
  private ahoraLocal(): string {
    const ahora = new Date();
    return new Date(ahora.getTime() - ahora.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
  }

  get cheque() { return this.pago?.cheque ?? null; }
  get puedeGuardar(): boolean { return this.form.valid && !this.guardando; }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  confirmar(): void {
    if (!this.detalle || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    this.service.recibirCheque(this.detalle.sse_id, this.pago.spa_id, {
      confirma_monto: true,
      observacion: this.form.value.observacion || null,
    }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Cheque recibido');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores) {
          Object.entries(errores).forEach(([campo, m]) => this.form.get(campo)?.setErrors({ backend: m[0] }));
        }
        this.toastr.error(CobrosServicioService.mensajeError(err, 'No se pudo confirmar la recepción'), 'Error');
      },
    });
  }

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
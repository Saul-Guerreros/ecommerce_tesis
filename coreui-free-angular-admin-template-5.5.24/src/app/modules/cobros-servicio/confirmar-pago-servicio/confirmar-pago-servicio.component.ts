import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Modal «Confirmar pago» de un pago WEB del servicio. Espejo de
 * pedidos/confirmar-pago (misma validación asimétrica del monto: de menos
 * se rechaza, de más se confirma explícitamente), sin el bloqueo por stock:
 * en el servicio el stock ya se descontó al cerrar la OT.
 */
@Component({
  selector: 'app-confirmar-pago-servicio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule],
  templateUrl: './confirmar-pago-servicio.component.html',
})
export class ConfirmarPagoServicioComponent implements OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() pago: PagoServicio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();
  @Output() rechazar = new EventEmitter<PagoServicio>();

  form: FormGroup;
  guardando = false;
  montoTocado = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      monto_verificado: [null as number | null, [Validators.required, Validators.min(1)]],
      nro_transaccion: ['', Validators.maxLength(50)],
      banco: ['', Validators.maxLength(60)],
      fecha_pago: [''],
      observacion: ['', Validators.maxLength(255)],
      confirmar_diferencia: [false],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
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

  get montoEsperado(): number { return this.pago?.monto_asignado ?? 0; }
  get montoVerificado(): number { return Number(this.form.get('monto_verificado')?.value ?? 0); }
  get diferencia(): number { return this.montoVerificado - this.montoEsperado; }
  get faltante(): number { return Math.abs(this.diferencia); }
  get montoInsuficiente(): boolean { return this.montoTocado && this.montoVerificado > 0 && this.diferencia <= -1; }
  get montoExcedente(): boolean { return this.montoTocado && this.diferencia >= 1; }

  /** Si con este pago se cubre el total, el equipo queda habilitado para entregar (regla 7). */
  get cubreElTotal(): boolean {
    const r = this.detalle?.resumen;
    return !!r && r.consolidado && r.confirmado + this.montoEsperado >= r.total;
  }

  get puedeGuardar(): boolean {
    if (this.form.invalid || this.guardando || this.montoInsuficiente) return false;
    return !(this.montoExcedente && !this.form.get('confirmar_diferencia')?.value);
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  confirmar(): void {
    if (!this.detalle || !this.pago || !this.puedeGuardar) { this.form.markAllAsTouched(); return; }

    const v = this.form.value;
    this.guardando = true;

    this.service.confirmar(this.detalle.sse_id, this.pago.spa_id, {
      monto_verificado: Number(v.monto_verificado),
      nro_transaccion: v.nro_transaccion || null,
      banco: v.banco || null,
      fecha_pago: v.fecha_pago || null,
      observacion: v.observacion || null,
      confirmar_diferencia: !!v.confirmar_diferencia,
    }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Pago confirmado');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores) {
          Object.entries(errores).forEach(([campo, m]) => this.form.get(campo)?.setErrors({ backend: m[0] }));
          this.toastr.error(Object.values(errores)[0][0], 'No se pudo confirmar');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo confirmar el pago', 'Error');
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
    this.montoTocado = false;
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}

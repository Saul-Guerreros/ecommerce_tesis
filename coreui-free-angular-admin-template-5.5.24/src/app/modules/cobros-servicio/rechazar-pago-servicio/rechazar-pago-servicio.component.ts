import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, MotivoRechazo, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Modal «Rechazar pago» del servicio. Espejo de pedidos/rechazar-pago: el
 * detalle lo ve el cliente en C4 y en el correo, así que tiene que explicar
 * qué corregir. Con el motivo por monto, la diferencia se calcula sola.
 */
@Component({
  selector: 'app-rechazar-pago-servicio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule],
  templateUrl: './rechazar-pago-servicio.component.html',
})
export class RechazarPagoServicioComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() pago: PagoServicio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();

  form: FormGroup;
  motivos: MotivoRechazo[] = [];
  guardando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      motivo_id: [null as number | null, Validators.required],
      monto_verificado: [null as number | null],
      detalle: ['', Validators.maxLength(255)],
    });
  }

  ngOnInit(): void {
    this.service.config().subscribe({
      next: (res) => (this.motivos = res.data.motivos),
      error: () => this.toastr.error('No se pudieron cargar los motivos', 'Error'),
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({ motivo_id: null, monto_verificado: null, detalle: '' });
    }
  }

  get motivoSeleccionado(): MotivoRechazo | undefined {
    return this.motivos.find((m) => m.mrp_id === this.form.get('motivo_id')?.value);
  }

  get pideMonto(): boolean { return this.motivoSeleccionado?.mrp_codigo === 'MONTO_NO_COINCIDE'; }
  get detalleObligatorio(): boolean { return !!this.motivoSeleccionado?.mrp_requdet; }
  get montoEsperado(): number { return this.pago?.monto_asignado ?? 0; }
  get montoVerificado(): number { return Number(this.form.get('monto_verificado')?.value ?? 0); }
  get faltante(): number { return Math.max(0, this.montoEsperado - this.montoVerificado); }

  /** Efectivo "al retirar": no hay comprobante que corregir, el monto queda libre para otro cobro. */
  get esEfectivo(): boolean { return !this.pago?.metodo.requcom; }

  get puedeGuardar(): boolean {
    if (this.form.invalid || this.guardando) return false;
    if (this.detalleObligatorio && !String(this.form.get('detalle')?.value ?? '').trim()) return false;
    return !(this.pideMonto && this.montoVerificado <= 0);
  }

  onMotivoChange(): void {
    if (!this.pideMonto) this.form.patchValue({ monto_verificado: null });
    const control = this.form.get('detalle');
    if (!control?.value && this.motivoSeleccionado) control?.setValue(this.motivoSeleccionado.mrp_descri);
  }

  onMontoChange(): void {
    if (!this.pideMonto || this.montoVerificado <= 0) return;
    this.form.patchValue({
      detalle: `El comprobante corresponde a Gs. ${this.formatoGs(this.montoVerificado)}. `
        + `El monto declarado es Gs. ${this.formatoGs(this.montoEsperado)}. `
        + `Diferencia faltante: Gs. ${this.formatoGs(this.faltante)}.`,
    });
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  rechazar(): void {
    if (!this.detalle || !this.pago || !this.puedeGuardar) { this.form.markAllAsTouched(); return; }

    const v = this.form.value;
    this.guardando = true;

    this.service.rechazar(this.detalle.sse_id, this.pago.spa_id, {
      motivo_id: Number(v.motivo_id),
      detalle: v.detalle || null,
      monto_verificado: this.pideMonto ? Number(v.monto_verificado) : null,
    }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Pago rechazado');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores) {
          Object.entries(errores).forEach(([campo, m]) => this.form.get(campo)?.setErrors({ backend: m[0] }));
          this.toastr.error(Object.values(errores)[0][0], 'No se pudo rechazar');
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

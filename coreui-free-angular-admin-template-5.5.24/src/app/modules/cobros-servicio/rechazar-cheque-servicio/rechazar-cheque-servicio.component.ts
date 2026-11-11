import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, MotivoRechazo, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Modal «Rechazar cheque» del servicio. Espejo de rechazar-cheque (pedidos)
 * con la única diferencia de negocio: NO cancela la solicitud. Rechaza ese
 * pago y el saldo vuelve a quedar pendiente. Motivos filtrados por ámbito.
 */
@Component({
  selector: 'app-rechazar-cheque-servicio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule],
  templateUrl: './rechazar-cheque-servicio.component.html',
})
export class RechazarChequeServicioComponent implements OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() pago: PagoServicio | null = null;
  @Input() motivos: MotivoRechazo[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      motivo_id: [null, [Validators.required]],
      detalle: ['', [Validators.maxLength(300)]],
      confirma: [false, [Validators.requiredTrue]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({ motivo_id: null, detalle: '', confirma: false });
    }
  }

  get motivoElegido(): MotivoRechazo | null {
    const id = Number(this.form.value.motivo_id);
    return this.motivos.find((m) => m.mrp_id === id) ?? null;
  }

  get detalleObligatorio(): boolean {
    return this.motivoElegido?.mrp_requdet ?? false;
  }

  get puedeGuardar(): boolean {
    if (!this.form.valid || this.guardando) return false;
    return !this.detalleObligatorio || !!String(this.form.value.detalle ?? '').trim();
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  rechazarCheque(): void {
    if (!this.detalle || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    this.service.rechazarCheque(this.detalle.sse_id, this.pago.spa_id, {
      motivo_id: Number(this.form.value.motivo_id),
      detalle: this.form.value.detalle || null,
      confirma: true,
    }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Cheque rechazado');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores) {
          Object.entries(errores).forEach(([campo, m]) => this.form.get(campo)?.setErrors({ backend: m[0] }));
        }
        this.toastr.error(CobrosServicioService.mensajeError(err, 'No se pudo rechazar el cheque'), 'Error');
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
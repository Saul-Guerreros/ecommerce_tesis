import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CuotaCobrar, MetodoPagoOption } from '../models/cuenta-por-cobrar.model';
import { CuentasPorCobrarService } from '../service/cuentas-por-cobrar.service';

/** Fecha de hoy en zona local: toISOString() devolvería la de UTC. */
function fechaLocal(d = new Date()): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

@Component({
  selector: 'app-form-cobro',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-cobro.component.html',
})
export class FormCobroComponent implements OnChanges {
  @Input() visible = false;
    @Input() cuotas: CuotaCobrar[] = [];
  @Input() metodos: MetodoPagoOption[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();
  @Output() cancelado = new EventEmitter<void>();

  readonly hoy = fechaLocal();
    /** Suma de las cuotas elegidas: es lo que va a decir el recibo. */
  get total(): number {
    return this.cuotas.reduce((suma, c) => suma + c.cco_monto, 0);
  }

  get numeros(): string {
    return this.cuotas.map(c => c.cco_nrocuo).join(', ');
  }

  form: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: CuentasPorCobrarService,
    private toastr: ToastrService,
  ) {
    // Sin monto: no hay pagos parciales, el importe es el de la cuota.
    this.form = this.fb.group({
      cob_mpaid:  [null, [Validators.required]],
      cob_fecha:  ['', [Validators.required]],
      cob_nrotrx: [''],
      cob_banco:  [''],
      cob_observ: [''],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
      if (changes['visible'] && this.visible && this.cuotas.length > 0) {
      this.guardando = false;
      this.form.reset({
        cob_mpaid:  this.metodos.length === 1 ? this.metodos[0].mpa_id : null,
        cob_fecha:  this.hoy,
        cob_nrotrx: '',
        cob_banco:  '',
        cob_observ: '',
      });
    }
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    const v = this.form.value;

    this.service.registrarCobro({
      cuotas:     this.cuotas.map(c => c.cco_id),
      rec_mpaid:  v.cob_mpaid,
      rec_fecha:  v.cob_fecha,
      rec_nrotrx: v.cob_nrotrx || null,
      rec_banco:  v.cob_banco || null,
      rec_observ: v.cob_observ || null,
    }).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Cobro registrado', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.ocultar();
      },
      error: (err) => {
        this.guardando = false;
        const mensaje =
          err?.error?.errors?.cobro?.[0] ??
          err?.error?.message ??
          'No se pudo registrar el cobro';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  /** Cierra sin avisar: lo usa guardar(), que ya emitió 'guardado'. */
  private ocultar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  cancelar(): void {
    this.ocultar();
    this.cancelado.emit();
  }
}
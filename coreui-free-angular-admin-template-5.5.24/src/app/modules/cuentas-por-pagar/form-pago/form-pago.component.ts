import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CuotaPendiente } from '../models/cuenta-por-pagar.model';
import { CuentasPorPagarService } from '../service/cuentas-por-pagar.service';

function fechaLocal(d = new Date()): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

@Component({
  selector: 'app-form-pago',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-pago.component.html',
})
export class FormPagoComponent implements OnChanges {
  @Input() visible = false;
  @Input() cuota: CuotaPendiente | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  readonly hoy = fechaLocal();
  readonly formasPago = ['Efectivo', 'Transferencia', 'Cheque', 'Otro'];

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: CuentasPorPagarService, private toastr: ToastrService) {
    this.form = this.fb.group({
      pgo_fecha:      ['', [Validators.required]],
      pgo_formapa:    ['Transferencia', [Validators.required]],
      pgo_referencia: [''],
      pgo_observ:     [''],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.cuota) {
      this.guardando = false;
      this.form.reset({ pgo_fecha: this.hoy, pgo_formapa: 'Transferencia', pgo_referencia: '', pgo_observ: '' });
    }
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    const v = this.form.value;
    this.service.registrarPago({
      cuo_id: this.cuota!.cuo_id,
      pgo_fecha: v.pgo_fecha,
      pgo_formapa: v.pgo_formapa,
      pgo_referencia: v.pgo_referencia || null,
      pgo_observ: v.pgo_observ || null,
    }).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Pago registrado', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar el pago', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
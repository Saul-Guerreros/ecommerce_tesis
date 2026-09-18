import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule } from '@coreui/angular';
import { CuotaPendiente } from '../models/cuenta-por-pagar.model';
import { CuentasPorPagarService } from '../service/cuentas-por-pagar.service';

@Component({
  selector: 'app-form-pago',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule],
  templateUrl: './form-pago.component.html',
})
export class FormPagoComponent implements OnChanges {
  @Input() visible = false;
  @Input() cuota: CuotaPendiente | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
  formasPago = ['Efectivo', 'Transferencia', 'Cheque', 'Otro'];

  constructor(private fb: FormBuilder, private service: CuentasPorPagarService, private toastr: ToastrService) {
    this.form = this.fb.group({
      pgo_fecha:   ['', [Validators.required]],
      pgo_importe: [null, [Validators.required, Validators.min(0.01)]],
      pgo_formapa: ['Transferencia', [Validators.required]],
      pgo_observ:  [''],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.cuota) {
      this.guardando = false;
      this.form.reset({
        pgo_fecha:   new Date().toISOString().substring(0, 10),
        pgo_importe: this.cuota.cuo_saldo,   // sugiere el saldo total; editable para pago parcial
        pgo_formapa: 'Transferencia',
        pgo_observ:  '',
      });
      // tope de importe = saldo pendiente de ESTA cuota (se recalcula por si cambia la cuota seleccionada)
      this.form.get('pgo_importe')?.setValidators([Validators.required, Validators.min(0.01), Validators.max(this.cuota.cuo_saldo)]);
      this.form.get('pgo_importe')?.updateValueAndValidity();
    }
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid || !this.cuota) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.service.registrarPago(this.cuota.cuo_id, this.form.value).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Pago registrado', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const control = this.form.get(campo);
            if (control) {
              control.setErrors({ backend: err.error.errors[campo][0] });
              control.markAsTouched();
            }
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar el pago', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
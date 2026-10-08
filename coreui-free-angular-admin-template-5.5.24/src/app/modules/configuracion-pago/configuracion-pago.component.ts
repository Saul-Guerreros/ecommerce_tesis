import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { ConfiguracionPagoService } from './service/configuracion-pago.service';

@Component({
  selector: 'app-configuracion-pago',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './configuracion-pago.component.html',
})
export class ConfiguracionPagoComponent implements OnInit {
  form: FormGroup;
  cargando = true;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: ConfiguracionPagoService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      ctn_pago_banco: ['', Validators.maxLength(80)],
      ctn_pago_banco_titular: ['', Validators.maxLength(150)],
      ctn_pago_banco_cuenta: ['', Validators.maxLength(40)],
      ctn_pago_tigo_titular: ['', Validators.maxLength(150)],
      ctn_pago_tigo_numero: ['', Validators.maxLength(20)],
      ctn_pago_tigo_referencia: ['', Validators.maxLength(60)],
      ctn_pago_efectivo_mensaje: ['', Validators.maxLength(200)],
    });
  }

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;
    this.service.getConfig().subscribe({
      next: (data) => {
        this.form.patchValue(data);
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar los datos de pago', 'Error');
        this.cargando = false;
      },
    });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.service.update(this.form.value).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Datos de pago actualizados.', 'Éxito');
        this.form.markAsPristine();
        this.guardando = false;
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar', 'Error');
      },
    });
  }
}
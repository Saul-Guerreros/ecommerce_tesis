import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, CardModule, FormModule, SpinnerModule, AlertModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { ConfiguracionEnvioService } from './service/configuracion-envio.service';

@Component({
  selector: 'app-configuracion-envio',
  standalone: true,
  imports: [
    CommonModule, RouterLink, ReactiveFormsModule,
    ButtonModule, CardModule, FormModule, SpinnerModule, AlertModule,
  ],
  templateUrl: './configuracion-envio.component.html',
})
export class ConfiguracionEnvioComponent implements OnInit {
  form: FormGroup;
  cargando = true;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: ConfiguracionEnvioService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      cfe_modo:         ['fijo', [Validators.required]],
      cfe_costo_fijo:   [null, [Validators.min(0)]],
      cfe_monto_minimo: [null, [Validators.min(0)]],
    });

    // El costo solo se exige cuando el modo es "fijo".
    this.form.get('cfe_modo')!.valueChanges.subscribe((modo) => {
      const costo = this.form.get('cfe_costo_fijo')!;
      costo.setValidators(
        modo === 'fijo'
          ? [Validators.required, Validators.min(1)]
          : [Validators.min(0)]
      );
      costo.updateValueAndValidity();
    });
  }

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;
    this.service.getConfig().subscribe({
      next: (data) => {
        this.form.patchValue({
          cfe_modo:         data.cfe_modo,
          cfe_costo_fijo:   data.cfe_costo_fijo || null,
          cfe_monto_minimo: data.cfe_monto_minimo || null,
        });
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar la configuración de envío', 'Error');
        this.cargando = false;
      },
    });
  }

  get esFijo(): boolean {
    return this.form.get('cfe_modo')?.value === 'fijo';
  }

  get esSucursal(): boolean {
    return this.form.get('cfe_modo')?.value === 'sucursal';
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
        this.toastr.success(res?.message ?? 'Configuración de envío actualizada.', 'Éxito');
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la configuración', 'Error');
      },
    });
  }
}
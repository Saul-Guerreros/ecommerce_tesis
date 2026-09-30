import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  AbstractControl,
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { ConfiguracionTiendaService } from './service/configuracion-tienda.service';

/** El campo numérico debe ser un entero (no 2,5 unidades). */
function entero(control: AbstractControl): ValidationErrors | null {
  const valor = control.value;
  return valor === null || valor === '' || Number.isInteger(valor) ? null : { entero: true };
}

/**
 * Configuración de la Tienda (singleton: una sola fila, sin listado).
 * Hoy administra los avisos de stock que ve el cliente en el detalle del
 * producto. Mismo patrón que Configuración del Margen.
 */
@Component({
  selector: 'app-configuracion-tienda',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './configuracion-tienda.component.html',
})
export class ConfiguracionTiendaComponent implements OnInit {
  form: FormGroup;
  cargando = true;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: ConfiguracionTiendaService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      ctn_pocstk_activo: [true],
      // Se valida siempre, aunque el aviso esté apagado: así, al volver a
      // encenderlo, ya tiene un valor válido (el backend también lo exige).
       ctn_pocstk_umbral: [5, [Validators.required, Validators.min(1), Validators.max(1000), entero]],
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
        this.toastr.error('No se pudo cargar la configuración de la tienda', 'Error');
        this.cargando = false;
      },
    });
  }

  /** Vista previa: el aviso tal como lo verá el cliente al llegar al umbral. */
  get ejemploPocoStock(): string {
    const n = this.form.get('ctn_pocstk_umbral')?.value;

    if (!Number.isInteger(n) || n < 1) {
      return 'Solo quedan … unidades';
    }

    return n === 1 ? 'Solo queda 1 unidad' : `Solo quedan ${n} unidades`;
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
        this.toastr.success(res?.message ?? 'Configuración de la tienda actualizada.', 'Éxito');
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

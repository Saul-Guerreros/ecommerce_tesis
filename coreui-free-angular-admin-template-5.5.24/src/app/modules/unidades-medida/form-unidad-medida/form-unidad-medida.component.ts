import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { UnidadMedida } from '../models/unidad-medida.model';
import { UnidadesMedidaService } from '../service/unidades-medida.service';

@Component({
  selector: 'app-form-unidad-medida',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-unidad-medida.component.html',
})
export class FormUnidadMedidaComponent implements OnChanges {
  @Input() visible = false;
  @Input() unidadMedida: UnidadMedida | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: UnidadesMedidaService, private toastr: ToastrService) {
    this.form = this.fb.group({
      umd_codigo:  ['', [Validators.required, Validators.maxLength(5)]],
      umd_nombre:  ['', [Validators.required, Validators.maxLength(40)]],
      umd_decimal: [0],
      umd_estado:  [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.unidadMedida?.umd_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        umd_codigo:  this.unidadMedida?.umd_codigo ?? '',
        umd_nombre:  this.unidadMedida?.umd_nombre ?? '',
        umd_decimal: this.unidadMedida?.umd_decimal ?? 0,
        umd_estado:  this.unidadMedida?.umd_estado ?? 1,
      });
      this.guardando = false;
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
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    const peticion = this.esEdicion
      ? this.service.update(this.unidadMedida!.umd_id!, this.form.value)
      : this.service.create(this.form.value);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la unidad de medida', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
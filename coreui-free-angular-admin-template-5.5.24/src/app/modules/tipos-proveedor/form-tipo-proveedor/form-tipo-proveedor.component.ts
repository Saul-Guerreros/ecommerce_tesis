import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoProveedor } from '../models/tipo-proveedor.model';
import { TiposProveedorService } from '../service/tipos-proveedor.service';

@Component({
  selector: 'app-form-tipo-proveedor',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tipo-proveedor.component.html',
})
export class FormTipoProveedorComponent implements OnChanges {
  @Input() visible = false;
  @Input() tipoProveedor: TipoProveedor | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: TiposProveedorService, private toastr: ToastrService) {
    this.form = this.fb.group({
      tpr_descri: ['', [Validators.required, Validators.maxLength(45)]],
      tpr_estado: [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.tipoProveedor?.tpr_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tpr_descri: this.tipoProveedor?.tpr_descri ?? '',
        tpr_estado: this.tipoProveedor?.tpr_estado ?? 1,
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
      ? this.service.update(this.tipoProveedor!.tpr_id!, this.form.value)
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el tipo de proveedor', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
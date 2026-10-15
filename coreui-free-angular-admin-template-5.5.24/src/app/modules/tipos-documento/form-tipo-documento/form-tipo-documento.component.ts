import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoDocumento } from '../models/tipo-documento.model';
import { TiposDocumentoService } from '../service/tipos-documento.service';

@Component({
  selector: 'app-form-tipo-documento',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tipo-documento.component.html',
})
export class FormTipoDocumentoComponent implements OnChanges {
  @Input() visible = false;
  @Input() tipoDocumento: TipoDocumento | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: TiposDocumentoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      tpd_codigo: ['', [Validators.required, Validators.maxLength(10), Validators.pattern(/^[A-Za-z0-9._-]+$/)]],
      tpd_descri: ['', [Validators.required, Validators.maxLength(50)]],
      tpd_estado: [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.tipoDocumento?.tpd_id;
  }

  /** CI y RUC los usa el sistema por código: solo se puede editar la descripción. */
  get esProtegido(): boolean {
    return this.esEdicion && !!this.tipoDocumento?.protegido;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tpd_codigo: this.tipoDocumento?.tpd_codigo ?? '',
        tpd_descri: this.tipoDocumento?.tpd_descri ?? '',
        tpd_estado: this.tipoDocumento?.tpd_estado ?? 1,
      });

      if (this.esProtegido) {
        this.form.get('tpd_codigo')?.disable();
        this.form.get('tpd_estado')?.disable();
      } else {
        this.form.get('tpd_codigo')?.enable();
        this.form.get('tpd_estado')?.enable();
      }
      this.guardando = false;
    }
  }

  /** El código se escribe siempre en mayúsculas. */
  mayusculas(): void {
    const control = this.form.get('tpd_codigo');
    const valor = control?.value ?? '';
    if (valor !== valor.toUpperCase()) {
      control?.setValue(valor.toUpperCase(), { emitEvent: false });
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

    // getRawValue: incluye los campos deshabilitados (código y estado de CI/RUC)
    const datos = this.form.getRawValue();

    const peticion = this.esEdicion
      ? this.service.update(this.tipoDocumento!.tpd_id!, datos)
      : this.service.create(datos);

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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el tipo de documento', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

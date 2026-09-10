import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Empresa } from '../models/empresa.model';
import { EmpresasService } from '../service/empresas.service';

/**
 * Modal único para alta y edición.
 * Si recibe [empresa] con emp_id -> modo edición (PUT).
 * Si recibe null -> modo alta (POST).
 */
@Component({
  selector: 'app-form-empresa',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-empresa.component.html',
})
export class FormEmpresaComponent implements OnChanges {
  @Input() visible = false;
  @Input() empresa: Empresa | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private empresasService: EmpresasService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      emp_razonsocial: ['', [Validators.required, Validators.maxLength(45)]],
      emp_ruc:         ['', [Validators.required, Validators.maxLength(15)]],
      emp_telef:       ['', [Validators.maxLength(20)]],
      emp_email:       ['', [Validators.email, Validators.maxLength(60)]],
      emp_actividad:   ['', [Validators.maxLength(50)]],
      emp_estado:      [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.empresa?.emp_id;
  }

  /**
   * Cada vez que el modal se abre se reinicia el formulario.
   * Esto evita el arrastre de datos entre una edición previa y un alta nueva.
   */
  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        emp_razonsocial: this.empresa?.emp_razonsocial ?? '',
        emp_ruc:         this.empresa?.emp_ruc ?? '',
        emp_telef:       this.empresa?.emp_telef ?? '',
        emp_email:       this.empresa?.emp_email ?? '',
        emp_actividad:   this.empresa?.emp_actividad ?? '',
        emp_estado:      this.empresa?.emp_estado ?? 1,
      });
      this.guardando = false;
    }
  }

  campoInvalido(campo: string): boolean {
    const control = this.form.get(campo);
    return !!control && control.invalid && (control.touched || control.dirty);
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    // Los campos opcionales vacíos se envían como null, no como ''.
    const datos: Partial<Empresa> = {
      ...this.form.value,
      emp_telef:     this.form.value.emp_telef || null,
      emp_email:     this.form.value.emp_email || null,
      emp_actividad: this.form.value.emp_actividad || null,
    };

    const peticion = this.esEdicion
      ? this.empresasService.update(this.empresa!.emp_id!, datos)
      : this.empresasService.create(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;

        // 422: errores de validación del backend, se muestran bajo cada campo.
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la empresa', 'Error');
      },
    });
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

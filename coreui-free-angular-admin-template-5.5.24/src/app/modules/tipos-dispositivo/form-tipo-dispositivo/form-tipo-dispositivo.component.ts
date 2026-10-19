import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoDispositivo } from '../models/tipo-dispositivo.model';
import { TiposDispositivoService } from '../service/tipos-dispositivo.service';

@Component({
  selector: 'app-form-tipo-dispositivo',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tipo-dispositivo.component.html',
})
export class FormTipoDispositivoComponent implements OnChanges {
  @Input() visible = false;
  @Input() tipoDispositivo: TipoDispositivo | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: TiposDispositivoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      tdi_codigo: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9_]+$/)]],
      tdi_descri: ['', [Validators.required, Validators.maxLength(50)]],
      tdi_estado: [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.tipoDispositivo?.tdi_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ tdi_codigo: '', tdi_descri: '', tdi_estado: 1 });
      if (this.esEdicion) {
        this.form.patchValue({
          tdi_codigo: this.tipoDispositivo!.tdi_codigo,
          tdi_descri: this.tipoDispositivo!.tdi_descri,
          tdi_estado: this.tipoDispositivo!.tdi_estado ?? 1,
        });
      }
      this.guardando = false;
    }
  }

  mayusculas(): void {
    const control = this.form.get('tdi_codigo');
    const valor = (control?.value ?? '').toUpperCase().replace(/\s+/g, '_');
    if (valor !== control?.value) control?.setValue(valor, { emitEvent: false });
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
    const datos = this.form.getRawValue();

    const peticion = this.esEdicion
      ? this.service.update(this.tipoDispositivo!.tdi_id!, datos)
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el tipo de dispositivo', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

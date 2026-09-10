import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoIva } from '../models/tipo-iva.model';
import { TiposIvaService } from '../service/tipos-iva.service';

@Component({
  selector: 'app-form-tipo-iva',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tipo-iva.component.html',
})
export class FormTipoIvaComponent implements OnChanges {
  @Input() visible = false;
  @Input() tipoIva: TipoIva | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private tiposIvaService: TiposIvaService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      tiv_descri: ['', [Validators.required, Validators.maxLength(45)]],
      tiv_tasa:   [null, [Validators.required, Validators.min(0), Validators.max(100)]],
      tiv_estado: [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.tipoIva?.tiv_id;
  }

  // La tasa de un IVA ya aplicado no se puede cambiar: alteraria el
  // desglose de documentos emitidos. El backend tambien lo rechaza.
  get bloquearTasa(): boolean {
    return this.esEdicion && (this.tipoIva?.usos_count ?? 0) > 0;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tiv_descri: this.tipoIva?.tiv_descri ?? '',
        tiv_tasa:   this.tipoIva?.tiv_tasa ?? null,
        tiv_estado: this.tipoIva?.tiv_estado ?? 1,
      });

      this.bloquearTasa ? this.form.get('tiv_tasa')?.disable() : this.form.get('tiv_tasa')?.enable();
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

    // getRawValue incluye los controles deshabilitados: la tasa bloqueada
    // debe viajar igual para que la validacion del backend la compare.
    const datos: Partial<TipoIva> = { ...this.form.getRawValue() };

    const peticion = this.esEdicion
      ? this.tiposIvaService.update(this.tipoIva!.tiv_id!, datos)
      : this.tiposIvaService.create(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operacion realizada', 'Exito');
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
          this.toastr.error('Revise los datos ingresados', 'Validacion');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el tipo de IVA', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Falla, TipoDispositivoOpcion } from '../models/falla.model';
import { FallasService } from '../service/fallas.service';

@Component({
  selector: 'app-form-falla',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-falla.component.html',
})
export class FormFallaComponent implements OnChanges {
  @Input() visible = false;
  @Input() falla: Falla | null = null;
  /** Combo cargado una sola vez por el listado (config). */
  @Input() tiposDispositivo: TipoDispositivoOpcion[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: FallasService, private toastr: ToastrService) {
    this.form = this.fb.group({
      fll_codigo: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9_]+$/)]],
      fll_descri: ['', [Validators.required, Validators.maxLength(100)]],
      fll_tdiid: [null as number | null],
      fll_estado: [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.falla?.fll_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ fll_codigo: '', fll_descri: '', fll_tdiid: null, fll_estado: 1 });
      if (this.esEdicion) {
        this.form.patchValue({
          fll_codigo: this.falla!.fll_codigo,
          fll_descri: this.falla!.fll_descri,
          fll_tdiid: this.falla!.fll_tdiid,
          fll_estado: this.falla!.fll_estado ?? 1,
        });
      }
      this.guardando = false;
    }
  }

  mayusculas(): void {
    const control = this.form.get('fll_codigo');
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
      ? this.service.update(this.falla!.fll_id!, datos)
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la falla', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

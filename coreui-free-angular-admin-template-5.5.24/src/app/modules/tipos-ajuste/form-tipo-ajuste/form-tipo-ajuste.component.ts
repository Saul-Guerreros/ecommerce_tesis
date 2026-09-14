import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoAjuste } from '../models/tipo-ajuste.model';
import { TiposAjusteService } from '../service/tipos-ajuste.service';

@Component({
  selector: 'app-form-tipo-ajuste',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tipo-ajuste.component.html',
})
export class FormTipoAjusteComponent implements OnChanges {
  @Input() visible = false;
  @Input() tipoAjuste: TipoAjuste | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: TiposAjusteService, private toastr: ToastrService) {
    this.form = this.fb.group({
      tpa_codig: ['', [Validators.required, Validators.maxLength(10)]],
      tpa_descri: ['', [Validators.required, Validators.maxLength(60)]],
      tpa_natura: ['P', [Validators.required]],
      tpa_reqcom: [0],
      tpa_estado: [1],
    });
  }

  get esEdicion(): boolean { return !!this.tipoAjuste?.tpa_id; }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tpa_codig: this.tipoAjuste?.tpa_codig ?? '',
        tpa_descri: this.tipoAjuste?.tpa_descri ?? '',
        tpa_natura: this.tipoAjuste?.tpa_natura ?? 'P',
        tpa_reqcom: this.tipoAjuste?.tpa_reqcom ?? 0,
        tpa_estado: this.tipoAjuste?.tpa_estado ?? 1,
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
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.guardando = true;

    const peticion = this.esEdicion
      ? this.service.update(this.tipoAjuste!.tpa_id!, this.form.value)
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el tipo de ajuste', 'Error');
      },
    });
  }

  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
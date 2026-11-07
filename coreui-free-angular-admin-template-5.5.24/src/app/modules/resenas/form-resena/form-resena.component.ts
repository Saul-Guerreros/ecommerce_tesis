import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { ResenaAdmin } from '../models/resena.model';
import { ResenasService } from '../service/resenas.service';

/**
 * Responder públicamente una reseña. Muestra la reseña completa (solo
 * lectura) y la respuesta editable. Guardar vacío = quitar la respuesta.
 */
@Component({
  selector: 'app-form-resena',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, BadgeModule],
  templateUrl: './form-resena.component.html',
})
export class FormResenaComponent implements OnChanges {
  readonly MAX_RESPUESTA = 1000;
  readonly ESTRELLAS = [1, 2, 3, 4, 5];

  @Input() visible = false;
  @Input() resena: ResenaAdmin | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: ResenasService, private toastr: ToastrService) {
    this.form = this.fb.group({
      res_respue: ['', [Validators.maxLength(this.MAX_RESPUESTA)]],
    });
  }

  get largoRespuesta(): number { return (this.form.get('res_respue')?.value ?? '').length; }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ res_respue: this.resena?.res_respue ?? '' });
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
    if (!this.resena || this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.guardando = true;

    const texto = (this.form.value.res_respue ?? '').trim() || null;

    this.service.responder(this.resena.res_id, texto).subscribe({
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la respuesta', 'Error');
      },
    });
  }

  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
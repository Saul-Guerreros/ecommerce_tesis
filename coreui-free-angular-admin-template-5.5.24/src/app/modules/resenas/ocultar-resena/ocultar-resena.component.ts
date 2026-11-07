import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { ResenaAdmin } from '../models/resena.model';
import { ResenasService } from '../service/resenas.service';

/** Ocultar una reseña visible: el motivo es obligatorio y lo ve el cliente. */
@Component({
  selector: 'app-ocultar-resena',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './ocultar-resena.component.html',
})
export class OcultarResenaComponent implements OnChanges {
  readonly MAX_MOTIVO = 255;
  readonly MOTIVOS_FRECUENTES = [
    'Lenguaje ofensivo o inapropiado',
    'Contiene datos personales',
    'No habla sobre el producto',
    'Contenido publicitario o spam',
  ];

  @Input() visible = false;
  @Input() resena: ResenaAdmin | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: ResenasService, private toastr: ToastrService) {
    this.form = this.fb.group({
      res_motocu: ['', [Validators.required, Validators.maxLength(this.MAX_MOTIVO)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ res_motocu: '' });
      this.guardando = false;
    }
  }

  usarMotivo(motivo: string): void {
    this.form.get('res_motocu')?.setValue(motivo);
    this.form.get('res_motocu')?.markAsDirty();
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

    this.service.cambiarEstado(this.resena.res_id, this.form.value.res_motocu.trim()).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Reseña ocultada', 'Éxito');
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
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo ocultar la reseña', 'Error');
      },
    });
  }

  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
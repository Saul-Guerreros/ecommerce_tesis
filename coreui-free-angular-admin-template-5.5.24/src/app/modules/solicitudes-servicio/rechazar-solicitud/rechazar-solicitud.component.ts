import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { SolicitudServicioLista } from '../models/solicitud-servicio.model';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';

@Component({
  selector: 'app-rechazar-solicitud',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './rechazar-solicitud.component.html',
})
export class RechazarSolicitudComponent implements OnChanges {
  @Input() visible = false;
  @Input() solicitud: SolicitudServicioLista | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: SolicitudesServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      motivo: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(255)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ motivo: '' });
      this.guardando = false;
    }
  }

  campoInvalido(): boolean {
    const c = this.form.get('motivo');
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(): string | null {
    return this.form.get('motivo')?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid || !this.solicitud) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando = true;

    this.service.rechazar(this.solicitud.sse_id, this.form.value.motivo).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          const msg = Object.values(err.error.errors as Record<string, string[]>)[0]?.[0];
          this.form.get('motivo')?.setErrors({ backend: msg });
          this.form.get('motivo')?.markAsTouched();
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo rechazar la solicitud', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

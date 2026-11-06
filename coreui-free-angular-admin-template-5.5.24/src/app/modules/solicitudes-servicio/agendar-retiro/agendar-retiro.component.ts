import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { SolicitudServicioLista } from '../models/solicitud-servicio.model';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';
import { esDomingo, hoyIso, proximoDiaHabilIso, sinDomingoValidator } from '../../../core/validators/fecha-servicio';
@Component({
  selector: 'app-agendar-retiro',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './agendar-retiro.component.html',
})
export class AgendarRetiroComponent implements OnChanges {
  @Input() visible = false;
  @Input() solicitud: SolicitudServicioLista | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
    readonly hoy = hoyIso();

  constructor(private fb: FormBuilder, private service: SolicitudesServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      sse_fecing: ['', [Validators.required, sinDomingoValidator]],
      coment: ['', Validators.maxLength(255)],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      // Propone la fecha que eligió el cliente (o la ya agendada); si cayó domingo, el lunes siguiente.
      const propuesta = this.solicitud?.sse_fecing ?? this.hoy;
      this.form.reset({ sse_fecing: esDomingo(propuesta) ? proximoDiaHabilIso(propuesta) : propuesta, coment: '' });
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
    if (this.form.invalid || !this.solicitud) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando = true;

    this.service.agendarRetiro(this.solicitud.sse_id, this.form.value).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const control = this.form.get(campo) ?? this.form.get('sse_fecing');
            control?.setErrors({ backend: err.error.errors[campo][0] });
            control?.markAsTouched();
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo agendar el retiro', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

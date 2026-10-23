import { Component, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subscription } from 'rxjs';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { PresupuestoResumen } from '../models/panel-servicio.model';
import { PanelServiciosService } from '../service/panel-servicios.service';

/**
 * Paso 2 - Aprobación asistida: el funcionario registra lo que el cliente decidió en el
 * mostrador, por teléfono o por WhatsApp. La observación es la evidencia (obligatoria).
 * Reutilizable: lo abren el panel general (A3) y el detalle de la solicitud.
 */
@Component({
  selector: 'app-registrar-respuesta',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './registrar-respuesta.component.html',
})
export class RegistrarRespuestaComponent implements OnChanges, OnDestroy {
  @Input() visible = false;
  @Input() solicitudId: number | null = null;
  @Input() numero: string | null = null;
  @Input() presupuesto: PresupuestoResumen | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  readonly canales = [
    { valor: 'MOSTRADOR', texto: 'En la sucursal (firmó el presupuesto)' },
    { valor: 'TELEFONO', texto: 'Por teléfono' },
    { valor: 'WHATSAPP', texto: 'Por WhatsApp' },
  ];

  form: FormGroup;
  guardando = false;
  private sub: Subscription;

  constructor(private fb: FormBuilder, private service: PanelServiciosService, private toastr: ToastrService) {
    this.form = this.fb.group({
      decision: ['APROBAR', Validators.required],
      canal: [null as string | null, Validators.required],
      obs: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(200)]],
      motivo: [''],
    });

    // El motivo solo se exige al rechazar.
    this.sub = this.form.get('decision')!.valueChanges.subscribe((d: string) => {
      const motivo = this.form.get('motivo')!;
      motivo.setValidators(d === 'RECHAZAR' ? [Validators.required, Validators.minLength(5), Validators.maxLength(200)] : []);
      motivo.updateValueAndValidity();
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ decision: 'APROBAR', canal: null, obs: '', motivo: '' });
      this.guardando = false;
    }
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  get rechaza(): boolean {
    return this.form.get('decision')!.value === 'RECHAZAR';
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.guardando || !this.solicitudId) return;
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const v = this.form.getRawValue();
    this.guardando = true;
    this.service.responderPresupuesto(this.solicitudId, {
      decision: v.decision, canal: v.canal, obs: v.obs.trim(), motivo: this.rechaza ? v.motivo.trim() : null,
    }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Éxito');
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, msgs]) => {
            const c = this.form.get(campo);
            if (c) {
              c.setErrors({ backend: msgs[0] });
              c.markAsTouched();
            } else {
              this.toastr.error(msgs[0], 'Validación');
            }
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la respuesta', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

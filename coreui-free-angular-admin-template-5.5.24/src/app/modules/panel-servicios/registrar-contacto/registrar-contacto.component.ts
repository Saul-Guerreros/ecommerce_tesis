import { Component, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subscription } from 'rxjs';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { MedioContacto } from '../models/panel-servicio.model';
import { PanelServiciosService } from '../service/panel-servicios.service';

/**
 * Paso 2 - "Registrar contacto con el cliente": deja un CONTACTO en el historial para que
 * "Esperando cliente" sea confiable. Tras usar el botón de WhatsApp se abre con el medio
 * precargado (decisión D.13-b): el clic no garantiza el envío, por eso lo confirma el funcionario.
 */
@Component({
  selector: 'app-registrar-contacto',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './registrar-contacto.component.html',
})
export class RegistrarContactoComponent implements OnChanges, OnDestroy {
  @Input() visible = false;
  @Input() solicitudId: number | null = null;
  @Input() numero: string | null = null;
  @Input() medioInicial: MedioContacto | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  readonly medios = [
    { valor: 'TELEFONO', texto: 'Teléfono' },
    { valor: 'WHATSAPP', texto: 'WhatsApp' },
    { valor: 'CORREO', texto: 'Correo' },
    { valor: 'PRESENCIAL', texto: 'Presencial' },
  ];
  readonly resultados = [
    { valor: 'CONTACTADO', texto: 'Contactado' },
    { valor: 'NO_CONTESTA', texto: 'No contesta' },
    { valor: 'PIDE_TIEMPO', texto: 'Pide tiempo' },
  ];

  form: FormGroup;
  guardando = false;
  private sub: Subscription;

  constructor(private fb: FormBuilder, private service: PanelServiciosService, private toastr: ToastrService) {
    this.form = this.fb.group({
      medio: [null as string | null, Validators.required],
      resultado: [null as string | null, Validators.required],
      obs: ['', Validators.maxLength(300)],
    });

    // Si no contestó, la observación es obligatoria (igual que en el backend).
    this.sub = this.form.get('resultado')!.valueChanges.subscribe((r: string) => {
      const obs = this.form.get('obs')!;
      obs.setValidators(r === 'NO_CONTESTA' ? [Validators.required, Validators.maxLength(300)] : [Validators.maxLength(300)]);
      obs.updateValueAndValidity();
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ medio: this.medioInicial, resultado: null, obs: '' });
      this.guardando = false;
    }
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
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
    this.service.registrarContacto(this.solicitudId, { medio: v.medio, resultado: v.resultado, obs: v.obs?.trim() || null }).subscribe({
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
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar el contacto', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

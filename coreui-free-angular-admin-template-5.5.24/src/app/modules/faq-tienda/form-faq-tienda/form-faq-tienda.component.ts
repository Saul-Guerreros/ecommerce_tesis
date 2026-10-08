import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { FaqTienda, TemaFaq } from '../models/faq-tienda.model';
import { FaqTiendaService } from '../service/faq-tienda.service';

@Component({
  selector: 'app-form-faq-tienda',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-faq-tienda.component.html',
})
export class FormFaqTiendaComponent implements OnChanges {
  readonly MAX_RESPUESTA = 1000;

  @Input() visible = false;
  @Input() faq: FaqTienda | null = null;
  @Input() temas: TemaFaq[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: FaqTiendaService, private toastr: ToastrService) {
    this.form = this.fb.group({
      fqt_tema: ['', [Validators.required]],
      fqt_pregunta: ['', [Validators.required, Validators.maxLength(255)]],
      fqt_respuesta: ['', [Validators.required, Validators.maxLength(this.MAX_RESPUESTA)]],
      fqt_orden: [0, [Validators.min(0), Validators.max(999)]],
      fqt_activo: [true],
    });
  }

  get esEdicion(): boolean { return !!this.faq?.fqt_id; }

  get largoRespuesta(): number { return (this.form.get('fqt_respuesta')?.value ?? '').length; }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        fqt_tema: this.faq?.fqt_tema ?? '',
        fqt_pregunta: this.faq?.fqt_pregunta ?? '',
        fqt_respuesta: this.faq?.fqt_respuesta ?? '',
        fqt_orden: this.faq?.fqt_orden ?? 0,
        fqt_activo: this.faq?.fqt_activo ?? true,
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
      ? this.service.update(this.faq!.fqt_id!, this.form.value)
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la información', 'Error');
      },
    });
  }

  cerrar(): void { this.visible = false; this.visibleChange.emit(false); }
}
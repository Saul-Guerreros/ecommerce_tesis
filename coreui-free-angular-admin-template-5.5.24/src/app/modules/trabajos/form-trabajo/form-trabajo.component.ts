import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoIvaOpcion, Trabajo } from '../models/trabajo.model';
import { TrabajosService } from '../service/trabajos.service';

@Component({
  selector: 'app-form-trabajo',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-trabajo.component.html',
})
export class FormTrabajoComponent implements OnChanges {
  @Input() visible = false;
  @Input() trabajo: Trabajo | null = null;
  @Input() tiposIva: TipoIvaOpcion[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: TrabajosService, private toastr: ToastrService) {
    this.form = this.fb.group({
      trb_codigo: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9_]+$/)]],
      trb_descri: ['', [Validators.required, Validators.maxLength(100)]],
      trb_precio: [0, [Validators.required, Validators.min(0)]],
      trb_tivid: [null as number | null, Validators.required],
      trb_estado: [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.trabajo?.trb_id;
  }

  /** Por defecto IVA 10% al crear (lo habitual en mano de obra). */
  private ivaPorDefecto(): number | null {
    return this.tiposIva.find((t) => Number(t.tiv_tasa) === 10)?.tiv_id ?? null;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ trb_codigo: '', trb_descri: '', trb_precio: 0, trb_tivid: this.ivaPorDefecto(), trb_estado: 1 });
      if (this.esEdicion) {
        this.form.patchValue({
          trb_codigo: this.trabajo!.trb_codigo,
          trb_descri: this.trabajo!.trb_descri,
          trb_precio: this.trabajo!.trb_precio,
          trb_tivid: this.trabajo!.trb_tivid,
          trb_estado: this.trabajo!.trb_estado ?? 1,
        });
      }
      this.guardando = false;
    }
  }

  mayusculas(): void {
    const control = this.form.get('trb_codigo');
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
      ? this.service.update(this.trabajo!.trb_id!, datos)
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el trabajo', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

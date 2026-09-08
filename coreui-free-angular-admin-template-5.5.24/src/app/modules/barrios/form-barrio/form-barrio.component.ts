import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Barrio, CiudadOption } from '../models/barrio.model';
import { BarriosService } from '../service/barrios.service';

/**
 * Modal unico para alta y edicion.
 * Si recibe [barrio] con bar_id -> modo edicion (PUT).
 * Si recibe null -> modo alta (POST).
 */
@Component({
  selector: 'app-form-barrio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-barrio.component.html',
})
export class FormBarrioComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() barrio: Barrio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
  ciudades: CiudadOption[] = [];

  constructor(
    private fb: FormBuilder,
    private barriosService: BarriosService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      bar_ciuid:  [null, [Validators.required]],
      bar_descri: ['', [Validators.required, Validators.maxLength(50)]],
      bar_estado: [1],
    });
  }

  ngOnInit(): void {
    this.barriosService.getConfig().subscribe({
      next: (res) => (this.ciudades = res),
      error: () => this.toastr.error('No se pudieron cargar las ciudades', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.barrio?.bar_id;
  }

  /**
   * Cada vez que el modal se abre se reinicia el formulario: en modo alta
   * queda limpio y en modo edicion se puebla con el registro recibido.
   * Esto evita el arrastre de datos entre una edicion previa y un alta nueva.
   */
  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        bar_ciuid:  this.barrio?.bar_ciuid ?? null,
        bar_descri: this.barrio?.bar_descri ?? '',
        bar_estado: this.barrio?.bar_estado ?? 1,
      });
      this.guardando = false;
    }
  }

  campoInvalido(campo: string): boolean {
    const control = this.form.get(campo);
    return !!control && control.invalid && (control.touched || control.dirty);
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

    const peticion = this.esEdicion
      ? this.barriosService.update(this.barrio!.bar_id!, this.form.value)
      : this.barriosService.create(this.form.value);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operacion realizada', 'Exito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;

        // 422: errores de validacion del backend, se muestran bajo cada campo.
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validacion');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el barrio', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

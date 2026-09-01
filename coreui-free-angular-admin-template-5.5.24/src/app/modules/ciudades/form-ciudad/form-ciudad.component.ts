import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Ciudad, DepartamentoOption } from '../models/ciudad.model';
import { CiudadesService } from '../service/ciudades.service';

@Component({
  selector: 'app-form-ciudad',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-ciudad.component.html',
})
export class FormCiudadComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() ciudad: Ciudad | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
  departamentos: DepartamentoOption[] = [];

  constructor(
    private fb: FormBuilder,
    private ciudadesService: CiudadesService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      ciu_depid:  [null, [Validators.required]],
      ciu_descri: ['', [Validators.required, Validators.maxLength(50)]],
      ciu_estado: [1],
    });
  }

  ngOnInit(): void {
    this.ciudadesService.getDepartamentos().subscribe({
      next: (res) => (this.departamentos = res),
      error: () => this.toastr.error('No se pudieron cargar los departamentos', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.ciudad?.ciu_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        ciu_depid:  this.ciudad?.ciu_depid ?? null,
        ciu_descri: this.ciudad?.ciu_descri ?? '',
        ciu_estado: this.ciudad?.ciu_estado ?? 1,
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
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    const peticion = this.esEdicion
      ? this.ciudadesService.update(this.ciudad!.ciu_id!, this.form.value)
      : this.ciudadesService.create(this.form.value);

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

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la ciudad', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
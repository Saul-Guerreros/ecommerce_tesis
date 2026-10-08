import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TarifaEnvio, SucursalOption, CiudadOption } from '../models/tarifa-envio.model';
import { TarifaEnvioService } from '../service/tarifa-envio.service';

@Component({
  selector: 'app-form-tarifa-envio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tarifa-envio.component.html',
})
export class FormTarifaEnvioComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() tarifa: TarifaEnvio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
  sucursales: SucursalOption[] = [];
  ciudades: CiudadOption[] = [];

  constructor(
    private fb: FormBuilder,
    private service: TarifaEnvioService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      tev_sucid: [null, [Validators.required]],
      tev_ciuid: [null, [Validators.required]],
      tev_costo: [0, [Validators.required, Validators.min(0)]],
    });
  }

  ngOnInit(): void {
    this.service.getSucursales().subscribe({
      next: (res) => (this.sucursales = res),
      error: () => this.toastr.error('No se pudieron cargar las sucursales', 'Error'),
    });

    this.service.getCiudades().subscribe({
      next: (res) => (this.ciudades = res),
      error: () => this.toastr.error('No se pudieron cargar las ciudades', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.tarifa?.tev_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tev_sucid: this.tarifa?.tev_sucid ?? null,
        tev_ciuid: this.tarifa?.tev_ciuid ?? null,
        tev_costo: this.tarifa?.tev_costo ?? 0,
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

    const datos: Partial<TarifaEnvio> = { ...this.form.value };

    const peticion = this.esEdicion
      ? this.service.update(this.tarifa!.tev_id!, datos)
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

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la tarifa', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
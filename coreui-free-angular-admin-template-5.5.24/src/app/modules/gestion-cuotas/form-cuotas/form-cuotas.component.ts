import {
  Component,
  EventEmitter,
  Input,
  OnChanges,
  Output,
  SimpleChanges,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import {
  ButtonModule,
  FormModule,
  ModalModule,
  SpinnerModule,
  TableModule,
} from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import {
  ConfiguracionCuota,
  GuardarCuotaPayload,
  ProductoCuota,
} from '../models/configuracion-cuota.model';
import { GestionCuotasService } from '../service/gestion-cuotas.service';
import { DeleteCuotaComponent } from '../delete-cuota/delete-cuota.component';

@Component({
  selector: 'app-form-cuotas',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    ButtonModule,
    FormModule,
    ModalModule,
    SpinnerModule,
    TableModule,
    DeleteCuotaComponent,
  ],
  templateUrl: './form-cuotas.component.html',
})
export class FormCuotasComponent implements OnChanges {
  @Input() visible = false;
  @Input() producto: ProductoCuota | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  cuotas: ConfiguracionCuota[] = [];
  cargando = false;
  guardando = false;

  form: FormGroup;
  enEdicion: ConfiguracionCuota | null = null;

  deleteModalVisible = false;
  cuotaAEliminar: ConfiguracionCuota | null = null;

  constructor(
    private fb: FormBuilder,
    private service: GestionCuotasService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      cfc_cantidad: [
        null as number | null,
        [Validators.required, Validators.min(1), Validators.max(60)],
      ],
      cfc_recargo_pct: [
        null as number | null,
        [Validators.required, Validators.min(0), Validators.max(999.99)],
      ],
      cfc_estado: [true],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.producto) {
      this.cancelarEdicion();
      this.cargar();
    }
  }

  get precioContado(): number {
    return this.producto?.precio_contado ?? 0;
  }

  cargar(): void {
    if (!this.producto) {
      return;
    }

    this.cargando = true;

    this.service.getAll(this.producto.prd_id).subscribe({
      next: (res) => {
        this.cuotas = res;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las cuotas', 'Error');
        this.cuotas = [];
        this.cargando = false;
      },
    });
  }

  // Vista previa. Misma fórmula que el backend:
  //   financiado = contado + (contado x recargo / 100)
  //   cuota      = financiado / cantidad de cuotas
  get previsualizacion(): { financiado: number; cuota: number } | null {
    const cantidad = Number(this.form.value.cfc_cantidad);
    const recargo = Number(this.form.value.cfc_recargo_pct);

    if (!cantidad || cantidad <= 0 || isNaN(recargo) || this.precioContado <= 0) {
      return null;
    }

    const financiado = Math.round(this.precioContado * (1 + recargo / 100));

    return { financiado, cuota: Math.round(financiado / cantidad) };
  }

  get esEdicion(): boolean {
    return this.enEdicion !== null;
  }

  abrirEditar(cuota: ConfiguracionCuota): void {
    this.enEdicion = cuota;

    this.form.reset({
      cfc_cantidad: cuota.cfc_cantidad,
      cfc_recargo_pct: cuota.cfc_recargo_pct,
      cfc_estado: cuota.cfc_estado,
    });
  }

  cancelarEdicion(): void {
    this.enEdicion = null;
    this.form.reset({ cfc_cantidad: null, cfc_recargo_pct: null, cfc_estado: true });
    this.guardando = false;
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid || !this.producto) {
      this.form.markAllAsTouched();
      return;
    }

    const payload: GuardarCuotaPayload = {
      cfc_prdid: this.producto.prd_id,
      cfc_cantidad: Number(this.form.value.cfc_cantidad),
      cfc_recargo_pct: Number(this.form.value.cfc_recargo_pct),
      cfc_estado: !!this.form.value.cfc_estado,
    };

    this.guardando = true;

    const peticion$ = this.esEdicion
      ? this.service.update(this.enEdicion!.cfc_id, payload)
      : this.service.create(payload);

    peticion$.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Cuota guardada', 'Éxito');
        this.cancelarEdicion();
        this.cargar();
        this.guardado.emit();
      },
      error: (err) => {
        this.guardando = false;

        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const control = this.form.get(campo);

            if (control) {
              control.setErrors({ backend: err.error.errors[campo][0] });
              control.markAsTouched();
            } else {
              // Errores que no mapean a un campo (ej. cfc_prdid: sin precio válido)
              this.toastr.error(err.error.errors[campo][0], 'Validación');
            }
          });

          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la cuota', 'Error');
      },
    });
  }

  cambiarEstado(cuota: ConfiguracionCuota): void {
    this.service.toggleEstado(cuota.cfc_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito');
        this.cargar();
        this.guardado.emit();
      },
      error: (err) =>
        this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }

  abrirEliminar(cuota: ConfiguracionCuota): void {
    this.cuotaAEliminar = cuota;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.cuotaAEliminar) {
      return;
    }

    const eliminado = this.cuotaAEliminar;

    this.service.delete(eliminado.cfc_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Cuota eliminada', 'Éxito');
        this.deleteModalVisible = false;
        this.cuotaAEliminar = null;

        if (this.enEdicion && this.enEdicion.cfc_id === eliminado.cfc_id) {
          this.cancelarEdicion();
        }

        this.cargar();
        this.guardado.emit();
      },
      error: (err) => {
        this.deleteModalVisible = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar la cuota', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
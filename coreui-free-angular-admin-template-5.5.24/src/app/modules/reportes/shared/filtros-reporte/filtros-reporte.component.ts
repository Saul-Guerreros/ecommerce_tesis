import {
  Component, computed, DestroyRef, EventEmitter, inject, Input, OnInit, Output, signal,
} from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import {
  AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, ValidatorFn, Validators,
} from '@angular/forms';
import {
  ButtonDirective, CardBodyComponent, CardComponent, ColComponent, FormControlDirective,
  FormLabelDirective, FormSelectDirective, RowComponent, SpinnerComponent,
} from '@coreui/angular';

import { ReportesService } from '../../service/reportes.service';
import {
  Agrupacion, FiltrosReporte, OpcionAgrupacion, OpcionDeposito, OpcionSucursal,
} from '../../models/reporte.model';
import { PRESETS_FECHA, PresetFecha, rangoPreset } from '../fechas';

const MAX_DIAS = 1096; // mismo límite que FiltroReporteRequest (3 años)

/** desde <= hasta y rango máximo de 3 años. */
const rangoValido: ValidatorFn = (grupo: AbstractControl): ValidationErrors | null => {
  const desde = grupo.get('desde')?.value;
  const hasta = grupo.get('hasta')?.value;

  if (!desde || !hasta) {
    return null;
  }
  if (desde > hasta) {
    return { rangoInvertido: true };
  }

  const dias = (new Date(hasta).getTime() - new Date(desde).getTime()) / 86_400_000;
  return dias > MAX_DIAS ? { rangoExcedido: true } : null;
};

/**
 * Filtros comunes de todos los reportes: rango de fechas (con atajos),
 * sucursal, depósito y agrupación. Cada reporte activa los que necesita
 * y puede proyectar filtros propios con el atributo filtrosExtra:
 *
 *   <app-filtros-reporte [mostrarAgrupacion]="true" (aplicar)="buscar($event)">
 *     <c-col md="3" filtrosExtra> ...select de proveedor... </c-col>
 *   </app-filtros-reporte>
 *
 * Los filtros propios los maneja el reporte; este componente emite solo
 * los comunes y el reporte los combina.
 */
@Component({
  selector: 'app-filtros-reporte',
  standalone: true,
  templateUrl: './filtros-reporte.component.html',
  imports: [
    ReactiveFormsModule, CardComponent, CardBodyComponent, RowComponent, ColComponent,
    FormLabelDirective, FormControlDirective, FormSelectDirective, ButtonDirective, SpinnerComponent,
  ],
})
export class FiltrosReporteComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  @Input() mostrarFechas = true;
  @Input() mostrarSucursal = true;
  @Input() mostrarDeposito = false;
  @Input() mostrarAgrupacion = false;
  /** Deshabilita "Aplicar" mientras el reporte consulta (evita doble envío). */
  @Input() cargando = false;
  /** Errores 422 del backend, mostrados junto al campo. */
  @Input() errores: Record<string, string[]> | null = null;
  /** Emitir los filtros por defecto al iniciar (carga inicial del reporte). */
  @Input() emitirAlIniciar = true;

  @Output() aplicar = new EventEmitter<FiltrosReporte>();

  readonly presets = PRESETS_FECHA;
  readonly presetActivo = signal<PresetFecha | null>('mes');

  readonly sucursales = signal<OpcionSucursal[]>([]);
  readonly depositos = signal<OpcionDeposito[]>([]);
  readonly agrupaciones = signal<OpcionAgrupacion[]>([]);
  readonly configError = signal(false);

  readonly form = this.fb.group(
    {
      desde: [rangoPreset('mes').desde, Validators.required],
      hasta: [rangoPreset('mes').hasta, Validators.required],
      sucursal_id: [null as number | null],
      deposito_id: [null as number | null],
      agrupacion: ['mes' as Agrupacion],
    },
    { validators: rangoValido },
  );

  private readonly sucursalSeleccionada = toSignal(this.form.controls.sucursal_id.valueChanges, {
    initialValue: null,
  });

  /** Solo los depósitos de la sucursal elegida (o todos si es "todas"). */
  readonly depositosFiltrados = computed(() => {
    const suc = this.sucursalSeleccionada();
    return suc ? this.depositos().filter((d) => d.sucursal_id === Number(suc)) : this.depositos();
  });

  ngOnInit(): void {
    if (!this.mostrarFechas) {
      this.form.controls.desde.clearValidators();
      this.form.controls.hasta.clearValidators();
      this.form.updateValueAndValidity();
    }

    this.reportes.getConfig()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (c) => {
          this.sucursales.set(c.sucursales);
          this.depositos.set(c.depositos);
          this.agrupaciones.set(c.agrupaciones);
        },
        error: () => this.configError.set(true),
      });

    // Si cambia la sucursal y el depósito elegido no le pertenece, se limpia.
    this.form.controls.sucursal_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        const dep = this.form.controls.deposito_id.value;
        if (dep && !this.depositosFiltrados().some((d) => d.id === Number(dep))) {
          this.form.controls.deposito_id.setValue(null);
        }
      });

    // Si el usuario edita una fecha a mano, ya no hay atajo activo.
    this.form.controls.desde.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.presetActivo.set(null));
    this.form.controls.hasta.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.presetActivo.set(null));

    if (this.emitirAlIniciar) {
      this.enviar();
    }
  }

  aplicarPreset(clave: PresetFecha): void {
    this.form.patchValue(rangoPreset(clave), { emitEvent: false });
    this.form.updateValueAndValidity();
    this.presetActivo.set(clave);
    this.enviar();
  }

  enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.aplicar.emit(this.valor());
  }

  limpiar(): void {
    this.form.reset({
      ...rangoPreset('mes'),
      sucursal_id: null,
      deposito_id: null,
      agrupacion: 'mes',
    }, { emitEvent: false });
    this.presetActivo.set('mes');
    this.enviar();
  }

  /** Error de un campo: primero el del backend (422), luego el de validación local. */
  error(campo: 'desde' | 'hasta' | 'sucursal_id' | 'deposito_id' | 'agrupacion'): string | null {
    const backend = this.errores?.[campo]?.[0];
    if (backend) {
      return backend;
    }

    const ctrl = this.form.controls[campo];
    if (ctrl.touched && ctrl.hasError('required')) {
      return 'Campo obligatorio.';
    }
    if (campo === 'hasta' && (ctrl.touched || this.form.touched)) {
      if (this.form.hasError('rangoInvertido')) {
        return 'La fecha hasta no puede ser anterior a la fecha desde.';
      }
      if (this.form.hasError('rangoExcedido')) {
        return 'El rango no puede superar 3 años.';
      }
    }
    return null;
  }

  private valor(): FiltrosReporte {
    const v = this.form.getRawValue();

    return {
      ...(this.mostrarFechas ? { desde: v.desde ?? undefined, hasta: v.hasta ?? undefined } : {}),
        sucursal_id: this.mostrarSucursal && v.sucursal_id ? Number(v.sucursal_id) : null,
      ...(this.mostrarDeposito ? { deposito_id: v.deposito_id ? Number(v.deposito_id) : null } : {}),
      ...(this.mostrarAgrupacion ? { agrupacion: v.agrupacion ?? 'mes' } : {}),
    };
  }
}
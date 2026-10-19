import { Component, DestroyRef, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { OrdenConfig, OrdenTrabajo } from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';

interface Foto { archivo: File; preview: string; }

/**
 * T2 - Formulario de diagnóstico. Se puede guardar varias veces mientras la
 * orden está EN_DIAGNOSTICO (el backend reemplaza las fallas completas).
 */
@Component({
  selector: 'app-form-diagnostico',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './form-diagnostico.component.html',
})
export class FormDiagnosticoComponent implements OnChanges, OnDestroy {
  private readonly destroyRef = inject(DestroyRef);

  @Input({ required: true }) orden!: OrdenTrabajo;
  @Input({ required: true }) config!: OrdenConfig;
  @Output() guardado = new EventEmitter<OrdenTrabajo>();

  readonly MAX_MB = 5;
  form: FormGroup;
  fotos: Foto[] = [];
  errorFotos: string | null = null;
  guardando = false;

  constructor(private fb: FormBuilder, private service: OrdenesTecnicoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      fallas: this.fb.array([]),
      dia_causa: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(500)]],
      dia_repara: [true, Validators.required],
      dia_reqrep: [false],
      dia_tiempo: [1 as number | null, [Validators.required, Validators.min(1), Validators.max(365)]],
    });

    // No reparable => sin repuestos ni tiempo (mismo criterio que el CHECK de la tabla).
    this.form.get('dia_repara')!.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((repara: boolean) => {
      const tiempo = this.form.get('dia_tiempo')!;
      if (repara) {
        tiempo.setValidators([Validators.required, Validators.min(1), Validators.max(365)]);
        tiempo.enable({ emitEvent: false });
        this.form.get('dia_reqrep')!.enable({ emitEvent: false });
      } else {
        tiempo.clearValidators();
        tiempo.setValue(null, { emitEvent: false });
        tiempo.disable({ emitEvent: false });
        this.form.get('dia_reqrep')!.setValue(false, { emitEvent: false });
        this.form.get('dia_reqrep')!.disable({ emitEvent: false });
      }
      tiempo.updateValueAndValidity({ emitEvent: false });
    });
  }

  get fallas(): FormArray<FormGroup> {
    return this.form.get('fallas') as FormArray<FormGroup>;
  }

  get repara(): boolean {
    return this.form.get('dia_repara')!.value === true;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['orden'] && this.orden) {
      this.cargarDesdeOrden();
    }
  }

  ngOnDestroy(): void {
    this.fotos.forEach((f) => URL.revokeObjectURL(f.preview));
  }

  /** Puebla el formulario con el diagnóstico existente (edición) o lo deja vacío. */
  private cargarDesdeOrden(): void {
    const d = this.orden.diagnostico;
    this.fallas.clear();

    if (d) {
      d.fallas.forEach((f) => this.agregarFalla(f.fll_id, f.obs ?? ''));
      this.form.patchValue({
        dia_causa: d.dia_causa,
        dia_repara: d.dia_repara,
        dia_reqrep: d.dia_reqrep,
        dia_tiempo: d.dia_tiempo,
      });
    } else {
      this.agregarFalla();
      this.form.patchValue({ dia_causa: '', dia_repara: true, dia_reqrep: false, dia_tiempo: 1 });
    }
    this.form.markAsPristine();
    this.form.markAsUntouched();
  }

  agregarFalla(fllId: number | null = null, obs = ''): void {
    this.fallas.push(this.fb.group({
      fll_id: [fllId, Validators.required],
      obs: [obs, Validators.maxLength(255)],
    }));
  }

  quitarFalla(i: number): void {
    if (this.fallas.length > 1) this.fallas.removeAt(i);
  }

  /** Evita elegir dos veces la misma falla en filas distintas. */
  fallaUsada(fllId: number, filaActual: number): boolean {
    return this.fallas.controls.some((g, i) => i !== filaActual && g.value.fll_id === fllId);
  }

  // ---------- Fotos ----------

  onFotos(event: Event): void {
    const input = event.target as HTMLInputElement;
    const max = this.config?.max_fotos ?? 8;
    this.errorFotos = null;

    for (const archivo of Array.from(input.files ?? [])) {
      if (this.fotos.length >= max) { this.errorFotos = `Máximo ${max} fotos.`; break; }
      if (!/^image\/(jpeg|png|webp)$/.test(archivo.type)) { this.errorFotos = `"${archivo.name}" no es JPG, PNG o WEBP.`; continue; }
      if (archivo.size > this.MAX_MB * 1024 * 1024) { this.errorFotos = `"${archivo.name}" supera los ${this.MAX_MB} MB.`; continue; }
      this.fotos.push({ archivo, preview: URL.createObjectURL(archivo) });
    }
    input.value = '';
  }

  quitarFoto(i: number): void {
    URL.revokeObjectURL(this.fotos[i].preview);
    this.fotos.splice(i, 1);
  }

  // ---------- Validación y envío ----------

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
      this.toastr.warning('Complete los campos marcados.', 'Diagnóstico');
      return;
    }

    const v = this.form.getRawValue();
    const datos = new FormData();
    datos.append('dia_causa', v.dia_causa);
    datos.append('dia_repara', v.dia_repara ? '1' : '0');
    datos.append('dia_reqrep', v.dia_reqrep ? '1' : '0');
    if (v.dia_repara && v.dia_tiempo) datos.append('dia_tiempo', String(v.dia_tiempo));
    (v.fallas as { fll_id: number; obs: string }[]).forEach((f, i) => {
      datos.append(`fallas[${i}][fll_id]`, String(f.fll_id));
      if (f.obs) datos.append(`fallas[${i}][obs]`, f.obs);
    });
    this.fotos.forEach((f) => datos.append('fotos[]', f.archivo));

    this.guardando = true;
    this.service.guardarDiagnostico(this.orden.sse_id, datos).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Éxito');
        this.guardando = false;
        this.fotos.forEach((f) => URL.revokeObjectURL(f.preview));
        this.fotos = [];
        this.guardado.emit(res.data);
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, msgs]) => {
            if (campo.startsWith('fotos')) { this.errorFotos = msgs[0]; return; }
            const filaFalla = campo.match(/^fallas\.(\d+)\.(\w+)$/);
            const control = filaFalla
              ? this.fallas.at(+filaFalla[1])?.get(filaFalla[2])
              : this.form.get(campo);
            if (control) {
              control.setErrors({ backend: msgs[0] });
              control.markAsTouched();
            } else {
              this.toastr.error(msgs[0], 'Validación');
            }
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el diagnóstico', 'Error');
      },
    });
  }
}

// src/app/modules/productos/equivalencia-producto/equivalencia-producto.component.ts
import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule } from '@coreui/angular';
import { ProductoEquivalencia, UnidadMedidaOption } from '../models/producto.model';
import { EquivalenciasService } from '../service/equivalencias.service';

const MSG_DUPLICADA = 'Ya existe una unidad alternativa con la misma equivalencia.';
const MSG_HEREDADA = 'Esta presentación ya la hereda del modelo base.';
const FACTOR_MAX = 999999.9999;

@Component({
  selector: 'app-equivalencia-producto',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule],
  templateUrl: './equivalencia-producto.component.html',
})
export class EquivalenciaProductoComponent implements OnChanges {
  @Input() productoId: number | null = null;
  @Input() unidadBaseId: number | null = null;
  @Input() unidadBaseNombre = 'unidades';
  @Input() unidades: UnidadMedidaOption[] = []; // catálogo completo, ya lo carga form-producto
  @Input() soloLectura = false;
  // Herencia: una variante usa las presentaciones del modelo base + las suyas.
  @Input() esVariante = false;
  @Input() cantidadVariantes = 0; // solo informativo, para el modelo base

  equivalencias: ProductoEquivalencia[] = [];
  cargando = false;
  guardando = false;
  procesandoId: number | null = null;

  // Alta
  form: FormGroup;
  duplicadaInactiva: ProductoEquivalencia | null = null;

  // Edición inline del factor
  editandoId: number | null = null;
  factorEdicion = new FormControl<number | null>(null, [
    Validators.required, Validators.min(0.0001), Validators.max(FACTOR_MAX),
  ]);

  // Confirmación inline de eliminación
  confirmandoEliminarId: number | null = null;

  constructor(
    private fb: FormBuilder,
    private service: EquivalenciasService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      peq_umdid: [null as number | null, Validators.required],
      peq_factor: [null as number | null, [Validators.required, Validators.min(0.0001), Validators.max(FACTOR_MAX)]],
    });

    // Al tocar el formulario se descarta el aviso "está desactivada: reactivarla".
    this.form.valueChanges.pipe(takeUntilDestroyed()).subscribe(() => (this.duplicadaInactiva = null));
  }

  // Todo el catálogo MENOS la unidad de stock: una misma unidad puede repetirse con distinto factor.
  get unidadesDisponibles(): UnidadMedidaOption[] {
    return this.unidades.filter((u) => u.umd_id !== this.unidadBaseId);
  }

  get hayHeredadas(): boolean {
    return this.equivalencias.some((e) => e.heredada);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['productoId'] && this.productoId) {
      this.cargar();
    }
  }

  cargar(): void {
    if (!this.productoId) return;
    this.cargando = true;
    this.service.getByProducto(this.productoId).subscribe({
      next: (res) => { this.equivalencias = res; this.cargando = false; },
      error: () => { this.cargando = false; this.toastr.error('No se pudieron cargar las equivalencias', 'Error'); },
    });
  }

  // ==================== Alta ====================

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  agregar(): void {
    if (!this.productoId || this.guardando) return;
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }

    const umd = Number(this.form.value.peq_umdid);
    const factor = Number(this.form.value.peq_factor);

    // Capa Angular de la validación de duplicados (las otras son Laravel y PostgreSQL).
    const duplicada = this.buscarDuplicada(umd, factor);
    if (duplicada) {
      const control = this.form.get('peq_factor')!;
      control.setErrors({ backend: duplicada.heredada ? MSG_HEREDADA : MSG_DUPLICADA });
      control.markAsTouched();
      // Solo se ofrece reactivar las PROPIAS: una heredada se gestiona en el modelo base.
      this.duplicadaInactiva = duplicada.peq_estado === 0 && !duplicada.heredada ? duplicada : null;
      return;
    }

    this.guardando = true;
    this.service.create({ peq_prdid: this.productoId, peq_umdid: umd, peq_factor: factor }).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Equivalencia registrada correctamente', 'Éxito');
        this.form.reset({ peq_umdid: null, peq_factor: null });
        this.guardando = false;
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        if (!this.aplicarErrores422(err)) {
          this.toastr.error(err?.error?.message ?? 'No se pudo registrar la equivalencia', 'Error');
        }
      },
    });
  }

  reactivarDuplicada(): void {
    const eq = this.duplicadaInactiva;
    if (!eq) return;
    this.form.reset({ peq_umdid: null, peq_factor: null });
    this.cambiarEstado(eq);
  }

  // ==================== Edición del factor ====================

  editar(eq: ProductoEquivalencia): void {
    this.confirmandoEliminarId = null;
    this.editandoId = eq.peq_id ?? null;
    this.factorEdicion.reset(Number(eq.peq_factor));
  }

  cancelarEdicion(): void {
    this.editandoId = null;
    this.factorEdicion.reset(null);
  }

  get errorEdicion(): string | null {
    const c = this.factorEdicion;
    if (c.errors?.['backend']) return c.errors['backend'];
    return c.touched && c.invalid ? 'Debe ser un número mayor a 0.' : null;
  }

  guardarEdicion(eq: ProductoEquivalencia): void {
    if (!eq.peq_id || this.guardando) return;
    if (this.factorEdicion.invalid) { this.factorEdicion.markAsTouched(); return; }

    const factor = Number(this.factorEdicion.value);

    if (this.normalizar(factor) === this.normalizar(eq.peq_factor)) { this.cancelarEdicion(); return; } // sin cambios

    if (this.buscarDuplicada(eq.peq_umdid, factor, eq.peq_id)) {
      this.factorEdicion.setErrors({ backend: MSG_DUPLICADA });
      this.factorEdicion.markAsTouched();
      return;
    }

    this.guardando = true;
    this.service.update(eq.peq_id, { peq_prdid: eq.peq_prdid, peq_umdid: eq.peq_umdid, peq_factor: factor }).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Equivalencia actualizada correctamente', 'Éxito');
        this.guardando = false;
        this.cancelarEdicion();
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        const msg422 = err.error?.errors?.['peq_factor']?.[0];
        if (err.status === 422 && msg422) {
          this.factorEdicion.setErrors({ backend: msg422 });
          this.factorEdicion.markAsTouched();
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo actualizar la equivalencia', 'Error');
        if (err.status === 409) { this.cancelarEdicion(); this.cargar(); }
      },
    });
  }

  // ==================== Activar / desactivar ====================

  cambiarEstado(eq: ProductoEquivalencia): void {
    if (!eq.peq_id || this.procesandoId) return;
    this.procesandoId = eq.peq_id;
    this.service.toggleEstado(eq.peq_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.procesandoId = null; this.cargar(); },
      error: (err) => { this.procesandoId = null; this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'); },
    });
  }

  // ==================== Eliminación (solo si nunca se usó) ====================

  pedirEliminar(eq: ProductoEquivalencia): void {
    this.cancelarEdicion();
    this.confirmandoEliminarId = eq.peq_id ?? null;
  }

  cancelarEliminar(): void {
    this.confirmandoEliminarId = null;
  }

  confirmarEliminar(eq: ProductoEquivalencia): void {
    if (!eq.peq_id || this.procesandoId) return;
    this.procesandoId = eq.peq_id;
    this.service.delete(eq.peq_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Equivalencia eliminada correctamente', 'Éxito');
        this.procesandoId = null;
        this.confirmandoEliminarId = null;
        this.cargar();
      },
      error: (err) => {
        this.procesandoId = null;
        this.confirmandoEliminarId = null;
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar la equivalencia', 'Error');
        if (err.status === 409) this.cargar();
      },
    });
  }

  // ==================== Auxiliares ====================

  private normalizar(n: number | string): number {
    return Math.round(Number(n) * 10000); // 4 decimales, igual que decimal(10,4)
  }

  private buscarDuplicada(umdid: number, factor: number, ignorarId?: number): ProductoEquivalencia | undefined {
    return this.equivalencias.find(
      (e) => e.peq_umdid === umdid && this.normalizar(e.peq_factor) === this.normalizar(factor) && e.peq_id !== ignorarId
    );
  }

  /** Devuelve true si al menos un error 422 se pudo mostrar junto a su campo. */
  private aplicarErrores422(err: HttpErrorResponse): boolean {
    if (err.status !== 422 || !err.error?.errors) return false;
    let mostrado = false;
    Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, msgs]) => {
      const control = this.form.get(campo);
      if (control) {
        control.setErrors({ backend: msgs[0] });
        control.markAsTouched();
        mostrado = true;
      }
    });
    return mostrado;
  }
}
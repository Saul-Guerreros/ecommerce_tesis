import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { OrdenTrabajo } from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';

/**
 * T4 - Ejecución y cierre. Una pantalla, dos bloques:
 *   1) Repuestos usados: cantidad real (0..aprobada).
 *   2) Cierre: procedimientos, resultado y garantía -> "Finalizar y marcar como listo".
 * El descuento de stock ocurre en el backend, en una sola transacción.
 */
/** Duración de la animación de cierre del modal de CoreUI (con margen). */
const MS_CIERRE_MODAL = 400;

@Component({
  selector: 'app-ejecucion-orden',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './ejecucion-orden.component.html',
})
export class EjecucionOrdenComponent implements OnChanges {
  @Input({ required: true }) orden!: OrdenTrabajo;
  @Output() actualizada = new EventEmitter<OrdenTrabajo>();

  form: FormGroup;
  guardandoCant = false;
  finalizando = false;
  confirmarVisible = false;

  constructor(private fb: FormBuilder, private service: OrdenesTecnicoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      repuestos: this.fb.array([]),
      ose_proced: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(5000)]],
      ose_result: ['REPARADO', Validators.required],
      ose_gardia: [90, [Validators.required, Validators.min(0), Validators.max(730)]],
    });
  }

  get repuestos(): FormArray<FormGroup> {
    return this.form.get('repuestos') as FormArray<FormGroup>;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['orden'] && this.orden) {
      this.repuestos.clear();
      this.orden.repuestos.forEach((r) => this.repuestos.push(this.fb.group({
        osr_id: [r.osr_id],
        cantid: [r.osr_cantid, [Validators.required, Validators.min(0), Validators.max(r.osr_cantap)]],
      })));
    }
  }

  get totalRepuestos(): number {
    return this.orden.repuestos.reduce((acc, r, i) => acc + (Number(this.repuestos.at(i)?.value.cantid) || 0) * r.osr_precio, 0);
  }

  private mapaCantidades(): Record<number, number> {
    const mapa: Record<number, number> = {};
    this.repuestos.controls.forEach((g) => (mapa[g.value.osr_id] = Number(g.value.cantid)));
    return mapa;
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardarCantidades(): void {
    if (this.repuestos.invalid) { this.repuestos.markAllAsTouched(); return; }
    this.guardandoCant = true;
    this.service.guardarRepuestos(this.orden.sse_id, this.mapaCantidades()).subscribe({
      next: (res) => { this.guardandoCant = false; this.toastr.success(res.message, 'Éxito'); this.actualizada.emit(res.data); },
      error: (err) => { this.guardandoCant = false; this.mostrarErrores(err, 'No se pudieron guardar las cantidades'); },
    });
  }

  pedirConfirmacion(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.warning('Complete los campos del cierre.', 'Finalizar');
      return;
    }
    this.confirmarVisible = true;
  }

  finalizar(): void {
    const v = this.form.getRawValue();
    this.finalizando = true;
    this.service.finalizar(this.orden.sse_id, {
      ose_proced: v.ose_proced,
      ose_result: v.ose_result,
      ose_gardia: Number(v.ose_gardia),
      repuestos: this.mapaCantidades(),
    }).subscribe({
      next: (res) => {
        this.confirmarVisible = false;
        this.toastr.success(res.message, 'Éxito');
        // Al quedar LISTA, el padre destruye este componente (*ngIf="acciones.ejecutar").
        // Si eso pasa con el modal todavía animando su cierre, CoreUI deja el backdrop
        // en el <body> y la pantalla queda bloqueada. Se espera a que termine la
        // animación antes de avisar al padre; `finalizando` sigue activo para evitar
        // un segundo envío en ese intervalo.
        setTimeout(() => {
          this.finalizando = false;
          this.actualizada.emit(res.data);
        }, MS_CIERRE_MODAL);
      },
      error: (err) => {
        this.finalizando = false;
        this.confirmarVisible = false;
        this.mostrarErrores(err, 'No se pudo finalizar la orden');
      },
    });
  }

  private mostrarErrores(err: any, porDefecto: string): void {
    const errores = err?.error?.errors as Record<string, string[]> | undefined;
    if (!errores) { this.toastr.error(err?.error?.message ?? porDefecto, 'Error'); return; }

    Object.entries(errores).forEach(([campo, msgs]) => {
      const fila = campo.match(/^repuestos\.(\d+)$/);
      if (fila) {
        // La clave del backend es el osr_id, no el índice.
        const g = this.repuestos.controls.find((c) => c.value.osr_id === +fila[1]);
        g?.get('cantid')?.setErrors({ backend: msgs[0] });
        g?.get('cantid')?.markAsTouched();
        this.toastr.error(msgs[0], 'Repuestos');
        return;
      }
      const control = this.form.get(campo);
      if (control) { control.setErrors({ backend: msgs[0] }); control.markAsTouched(); }
      else this.toastr.error(msgs[0], 'Error');
    });
  }
}
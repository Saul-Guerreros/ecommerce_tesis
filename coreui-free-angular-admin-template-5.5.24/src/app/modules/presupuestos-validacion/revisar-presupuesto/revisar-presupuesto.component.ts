import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { PresupuestoRevision } from '../models/presupuesto-validacion.model';
import { PresupuestosValidacionService } from '../service/presupuestos-validacion.service';
import { PresupuestoLinea } from '../../ordenes-tecnico/models/orden-tecnico.model';

/** Control de precios: compara cada línea con el precio de lista vigente. */
@Component({
  selector: 'app-revisar-presupuesto',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './revisar-presupuesto.component.html',
})
export class RevisarPresupuestoComponent implements OnChanges {
  @Input() visible = false;
  @Input() presupuestoId: number | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() procesado = new EventEmitter<void>();

  datos: PresupuestoRevision | null = null;
  cargando = false;
  procesando = false;
  modoDevolver = false;
  obs = new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.minLength(5), Validators.maxLength(255)] });

  constructor(private service: PresupuestosValidacionService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.presupuestoId) {
      this.datos = null;
      this.modoDevolver = false;
      this.obs.reset('');
      this.procesando = false;
      this.cargando = true;
      this.service.show(this.presupuestoId).subscribe({
        next: (res) => { this.datos = res.data; this.cargando = false; },
        error: () => { this.cargando = false; this.toastr.error('No se pudo cargar el presupuesto', 'Error'); },
      });
    }
  }

  get enRevision(): boolean {
    return this.datos?.presupuesto.estado.codigo === 'EN_REVISION';
  }

  difiere(l: PresupuestoLinea): boolean {
    return l.precio_lista !== null && l.precio_lista !== undefined && Number(l.psd_precio) !== Number(l.precio_lista);
  }

  get lineasConDiferencia(): number {
    return this.datos?.presupuesto.detalles.filter((l) => this.difiere(l)).length ?? 0;
  }

  validar(): void {
    if (!this.presupuestoId) return;
    this.procesando = true;
    this.service.validar(this.presupuestoId).subscribe({
      next: (res) => { this.toastr.success(res.message, 'Éxito'); this.procesando = false; this.procesado.emit(); this.cerrar(); },
      error: (err) => { this.procesando = false; this.error(err, 'No se pudo validar el presupuesto'); },
    });
  }

  devolver(): void {
    if (this.obs.invalid || !this.presupuestoId) { this.obs.markAsTouched(); return; }
    this.procesando = true;
    this.service.devolver(this.presupuestoId, this.obs.value).subscribe({
      next: (res) => { this.toastr.success(res.message, 'Éxito'); this.procesando = false; this.procesado.emit(); this.cerrar(); },
      error: (err) => {
        this.procesando = false;
        if (err.status === 422 && err.error?.errors?.obs) { this.obs.setErrors({ backend: err.error.errors.obs[0] }); return; }
        this.error(err, 'No se pudo devolver el presupuesto');
      },
    });
  }

  private error(err: any, porDefecto: string): void {
    const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
    this.toastr.error(msg ?? err?.error?.message ?? porDefecto, 'Error');
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

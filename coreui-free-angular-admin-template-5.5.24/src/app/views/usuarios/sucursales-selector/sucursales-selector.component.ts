import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SucursalCatalogo } from '../usuario.model';

/**
 * Selector de sucursales de un usuario: checkboxes + "sucursal principal"
 * (solo cuando hay más de una marcada). El backend exige al menos una.
 *
 * Uso:
 *   <app-sucursales-selector [sucursales]="sucursales"
 *       [(seleccion)]="ids" [(principal)]="principalId"></app-sucursales-selector>
 */
@Component({
  selector: 'app-sucursales-selector',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <label class="form-label">Sucursales asignadas (*)</label>
    <div class="border rounded p-2">
      <div class="form-check" *ngFor="let s of sucursales">
        <input class="form-check-input" type="checkbox"
               [id]="uid + '-' + s.suc_id"
               [checked]="seleccion.includes(s.suc_id)"
               (change)="alternar(s.suc_id, $any($event.target).checked)" />
        <label class="form-check-label" [for]="uid + '-' + s.suc_id">{{ s.suc_nombre }}</label>
      </div>
      <div class="text-muted small" *ngIf="sucursales.length === 0">
        No hay sucursales activas. Créalas primero en Configuración &gt; Empresa &gt; Sucursales.
      </div>
    </div>

    <div class="mt-2" *ngIf="seleccion.length > 1">
      <label class="form-label">Sucursal principal</label>
      <select class="form-select" [ngModel]="principal" [ngModelOptions]="{ standalone: true }"
              (ngModelChange)="principalChange.emit($event)">
        <option *ngFor="let s of marcadas" [ngValue]="s.suc_id">{{ s.suc_nombre }}</option>
      </select>
    </div>
  `,
})
export class SucursalesSelectorComponent {
  private static contador = 0;
  /** Prefijo único: los modales de crear y editar conviven en el DOM y los ids no deben repetirse. */
  readonly uid = 'suc' + ++SucursalesSelectorComponent.contador;

  @Input() sucursales: SucursalCatalogo[] = [];

  @Input() seleccion: number[] = [];
  @Output() seleccionChange = new EventEmitter<number[]>();

  @Input() principal: number | null = null;
  @Output() principalChange = new EventEmitter<number | null>();

  get marcadas(): SucursalCatalogo[] {
    return this.sucursales.filter((s) => this.seleccion.includes(s.suc_id));
  }

  alternar(id: number, marcada: boolean): void {
    const nueva = marcada
      ? [...this.seleccion, id]
      : this.seleccion.filter((s) => s !== id);

    this.seleccionChange.emit(nueva);

    // La principal siempre debe ser una de las marcadas; con una sola, es esa.
    if (this.principal === null || !nueva.includes(this.principal)) {
      this.principalChange.emit(nueva[0] ?? null);
    }
  }
}
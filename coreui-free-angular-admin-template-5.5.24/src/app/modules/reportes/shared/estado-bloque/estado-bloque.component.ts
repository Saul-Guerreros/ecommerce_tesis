import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ButtonDirective, SpinnerComponent } from '@coreui/angular';

import { EstadoCarga } from '../../models/reporte.model';

/**
 * Envuelve un gráfico o tabla y muestra el estado de carga, vacío o error.
 * Si el estado es 'ok', proyecta el contenido.
 *
 *   <app-estado-bloque [estado]="bloque().estado" (reintentar)="cargar()">
 *     <c-chart ... />
 *   </app-estado-bloque>
 */
@Component({
  selector: 'app-estado-bloque',
  standalone: true,
  imports: [SpinnerComponent, ButtonDirective],
  template: `
    @switch (estado) {
      @case ('cargando') {
        <div class="d-flex justify-content-center align-items-center text-body-secondary"
             [style.min-height.px]="altura">
          <c-spinner size="sm" color="primary" class="me-2" />
          Cargando...
        </div>
      }
      @case ('error') {
        <div class="d-flex flex-column justify-content-center align-items-center text-danger gap-2"
             [style.min-height.px]="altura">
          <span>No se pudo cargar la información.</span>
          <button cButton size="sm" color="danger" variant="outline" (click)="reintentar.emit()">
            Reintentar
          </button>
        </div>
      }
      @case ('vacio') {
        <div class="d-flex justify-content-center align-items-center text-body-secondary text-center"
             [style.min-height.px]="altura">
          {{ mensajeVacio }}
        </div>
      }
      @default {
        <ng-content />
      }
    }
  `,
})
export class EstadoBloqueComponent {
  @Input({ required: true }) estado: EstadoCarga = 'cargando';
  @Input() altura = 150;
  @Input() mensajeVacio = 'Sin datos para el período seleccionado.';
  @Output() reintentar = new EventEmitter<void>();
}
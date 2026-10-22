import { Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';

/**
 * Encabezado común de todos los reportes: título, descripción, volver
 * al inicio de Reportes y una zona de acciones (exportar PDF/Excel).
 *
 *   <app-pagina-reporte titulo="Reporte de ventas" descripcion="...">
 *     <div acciones> ...botones de exportación... </div>
 *     ...filtros y contenido...
 *   </app-pagina-reporte>
 */
@Component({
  selector: 'app-pagina-reporte',
  standalone: true,
  imports: [RouterLink],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
      <div>
        <a routerLink="/reportes" class="small text-decoration-none">&larr; Reportes</a>
        <h4 class="mb-0 mt-1">{{ titulo }}</h4>
        @if (descripcion) {
          <small class="text-body-secondary">{{ descripcion }}</small>
        }
      </div>
      <div class="d-flex flex-wrap gap-2">
        <ng-content select="[acciones]" />
      </div>
    </div>

    <ng-content />
  `,
})
export class PaginaReporteComponent {
  @Input({ required: true }) titulo = '';
  @Input() descripcion = '';
}
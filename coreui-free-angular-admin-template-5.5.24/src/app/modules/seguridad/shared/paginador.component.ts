import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ButtonModule } from '@coreui/angular';

/** Navegación entre páginas (solo cambia de página, no modifica datos). */
@Component({
  selector: 'app-paginador',
  standalone: true,
  imports: [CommonModule, ButtonModule],
  template: `
    <div class="d-flex justify-content-center mt-3" *ngIf="totalPaginas > 1">
      <button cButton size="sm" variant="outline" color="secondary"
              [disabled]="pagina === 1" (click)="cambio.emit(pagina - 1)">Anterior</button>
      <span class="mx-3 align-self-center">Página {{ pagina }} de {{ totalPaginas }}</span>
      <button cButton size="sm" variant="outline" color="secondary"
              [disabled]="pagina === totalPaginas" (click)="cambio.emit(pagina + 1)">Siguiente</button>
    </div>
  `,
})
export class PaginadorComponent {
  @Input() pagina = 1;
  @Input() total = 0;
  @Input() porPagina = 25;
  @Output() cambio = new EventEmitter<number>();

  get totalPaginas(): number {
    return Math.ceil(this.total / this.porPagina);
  }
}

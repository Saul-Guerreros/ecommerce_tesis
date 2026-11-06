import { Component, DestroyRef, inject, Input, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { ButtonDirective, SpinnerComponent } from '@coreui/angular';

import { ReportesService } from '../../service/reportes.service';

type Formato = 'pdf' | 'excel';

/**
 * Botones "PDF" y "Excel" de un reporte. Exporta con los ÚLTIMOS filtros
 * aplicados (los que corresponden a lo que se ve en pantalla).
 */
@Component({
  selector: 'app-exportar-reporte',
  standalone: true,
  imports: [ButtonDirective, SpinnerComponent],
  template: `
    <div class="d-flex flex-column align-items-end">
      <div class="d-flex gap-2">
        <button cButton size="sm" color="danger" class="text-white"
                [disabled]="deshabilitado || !filtros || exportando() !== null"
                (click)="exportar('pdf')">
          @if (exportando() === 'pdf') { <c-spinner size="sm" class="me-1" /> }
          Exportar PDF
        </button>
        <button cButton size="sm" color="success" class="text-white"
                [disabled]="deshabilitado || !filtros || exportando() !== null"
                (click)="exportar('excel')">
          @if (exportando() === 'excel') { <c-spinner size="sm" class="me-1" /> }
          Exportar Excel
        </button>
      </div>
      @if (error()) {
        <small class="text-danger mt-1">{{ error() }}</small>
      }
    </div>
  `,
})
export class ExportarReporteComponent {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  @Input({ required: true }) reporte = '';
  @Input({ required: true }) vista = '';
  @Input() filtros: object | null = null;
  @Input() deshabilitado = false;

  readonly exportando = signal<Formato | null>(null);
  readonly error = signal<string | null>(null);

  exportar(formato: Formato): void {
    if (!this.filtros) {
      return;
    }

    this.exportando.set(formato);
    this.error.set(null);

    this.reportes.exportar(this.reporte, this.vista, this.filtros, formato)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob) => {
          this.descargar(blob, formato);
          this.exportando.set(null);
        },
        error: (err: HttpErrorResponse) => {
          this.exportando.set(null);
          this.leerError(err);
        },
      });
  }

  private descargar(blob: Blob, formato: Formato): void {
    const fecha = new Date().toISOString().slice(0, 10);
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(blob);
    enlace.download = `reporte_${this.reporte}_${this.vista.replace('-', '_')}_${fecha}.${formato === 'pdf' ? 'pdf' : 'xlsx'}`;
    enlace.click();
    URL.revokeObjectURL(enlace.href);
  }

  /** Con responseType 'blob', el JSON de error también llega como Blob: hay que leerlo. */
  private leerError(err: HttpErrorResponse): void {
    const generico = 'No se pudo generar el archivo.';

    if (!(err.error instanceof Blob)) {
      this.error.set(generico);
      return;
    }

    err.error.text().then((texto) => {
      try {
        const json = JSON.parse(texto);
        const primero = json.errors ? Object.values(json.errors as Record<string, string[]>)[0]?.[0] : null;
        this.error.set(primero ?? json.message ?? generico);
      } catch {
        this.error.set(generico);
      }
    });
  }
}
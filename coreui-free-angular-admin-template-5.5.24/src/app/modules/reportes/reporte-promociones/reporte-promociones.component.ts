import { DatePipe, TitleCasePipe } from '@angular/common';
import { Component, computed, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { Observable, Subscription } from 'rxjs';
import {
  AlertComponent, BadgeComponent, CardBodyComponent, CardComponent, NavComponent, NavItemComponent,
  NavLinkDirective, TableDirective,
} from '@coreui/angular';

import { ReportesService } from '../service/reportes.service';
import { EstadoCarga, FiltrosReporte, RespuestaTabla } from '../models/reporte.model';
import { SituacionCampana, VistaPromociones } from '../models/promociones.model';
import { FiltrosReporteComponent } from '../shared/filtros-reporte/filtros-reporte.component';
import { PaginaReporteComponent } from '../shared/pagina-reporte/pagina-reporte.component';
import { EstadoBloqueComponent } from '../shared/estado-bloque/estado-bloque.component';
import { ExportarReporteComponent } from '../shared/exportar-reporte/exportar-reporte.component';

interface Resultado {
  estado: EstadoCarga;
  filas: any[];
  totales: Record<string, any> | null;
}

/**
 * Reporte de promociones (alcance acotado): configuración de cupones y
 * campañas. El uso real (pedidos con cupón / ventas por campaña) no es
 * medible porque el checkout no registra esa referencia: trabajo futuro.
 */
@Component({
  selector: 'app-reporte-promociones',
  standalone: true,
  templateUrl: './reporte-promociones.component.html',
  imports: [
    DatePipe, TitleCasePipe, PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent,
    ExportarReporteComponent, CardComponent, CardBodyComponent, NavComponent, NavItemComponent,
    NavLinkDirective, TableDirective, BadgeComponent, AlertComponent,
  ],
  styles: [`.tabla-scroll { max-height: 520px; overflow: auto; } thead th { position: sticky; top: 0; z-index: 1; }`],
})
export class ReportePromocionesComponent {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaPromociones; etiqueta: string }[] = [
    { clave: 'cupones', etiqueta: 'Cupones' },
    { clave: 'campanas', etiqueta: 'Campañas de descuento' },
  ];

  readonly vista = signal<VistaPromociones>('cupones');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);
  readonly ultimosFiltros = signal<object | null>(null);

  private filtrosBase: FiltrosReporte | null = null;
  private peticion?: Subscription;

  readonly cargando = computed(() => this.resultado().estado === 'cargando');
  /** Los cupones no tienen vigencia: solo las campañas usan el rango de fechas. */
  readonly usaFechas = computed(() => this.vista() === 'campanas');

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaPromociones): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const f = { ...this.filtrosBase };
    const peticiones: Record<VistaPromociones, () => Observable<RespuestaTabla<any, any>>> = {
      'cupones': () => this.reportes.promocionesCupones(f),
      'campanas': () => this.reportes.promocionesCampanas(f),
    };

    this.peticion?.unsubscribe();
    this.errores.set(null);
    this.ultimosFiltros.set(f);
    this.resultado.update((r) => ({ ...r, estado: 'cargando' }));

    this.peticion = peticiones[this.vista()]()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => this.resultado.set({
          estado: r.data.length === 0 ? 'vacio' : 'ok',
          filas: r.data,
          totales: r.meta.totales,
        }),
        error: (err: HttpErrorResponse) => {
          if (err.status === 422) {
            this.errores.set(err.error?.errors ?? null);
          }
          this.resultado.set({ estado: 'error', filas: [], totales: null });
        },
      });
  }

  fecha(valor: string | null): string | null {
    return valor ? `${valor.substring(0, 10)}T00:00:00` : null;
  }

  colorSituacion(s: SituacionCampana): string {
    return { vigente: 'success', programada: 'info', finalizada: 'secondary', inactiva: 'danger' }[s];
  }

  mensajeVacio(): string {
    return this.vista() === 'cupones'
      ? 'No hay cupones configurados.'
      : 'No hay campañas cuya vigencia se superponga con el período seleccionado.';
  }
}

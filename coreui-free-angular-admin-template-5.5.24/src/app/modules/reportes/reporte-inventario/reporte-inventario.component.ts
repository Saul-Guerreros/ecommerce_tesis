import { DatePipe } from '@angular/common';
import { Component, computed, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { Observable, Subscription } from 'rxjs';
import {
  BadgeComponent, CardBodyComponent, CardComponent, ColComponent, FormControlDirective,
  FormLabelDirective, FormSelectDirective, NavComponent, NavItemComponent, NavLinkDirective,
  ProgressComponent, RowComponent, TableDirective,
} from '@coreui/angular';

import { ReportesService } from '../service/reportes.service';
import {
  EstadoCarga, FiltrosReporte, OpcionCategoria, OpcionSimple, RespuestaTabla,
} from '../models/reporte.model';
import { FiltrosInventarioExtra, VistaInventario } from '../models/inventario.model';
import { FiltrosReporteComponent } from '../shared/filtros-reporte/filtros-reporte.component';
import { PaginaReporteComponent } from '../shared/pagina-reporte/pagina-reporte.component';
import { EstadoBloqueComponent } from '../shared/estado-bloque/estado-bloque.component';
import { GsPipe } from '../shared/gs.pipe';
import { formatoNumero } from '../shared/formato';
import { ExportarReporteComponent } from '../shared/exportar-reporte/exportar-reporte.component';

interface Resultado {
  estado: EstadoCarga;
  filas: any[];
  totales: Record<string, any> | null;
}

/**
 * Reporte de inventario: 4 vistas sobre el estado actual del stock.
 * Sin filtro de fechas (son fotos del momento); con depósito, categoría y marca.
 */
@Component({
  selector: 'app-reporte-inventario',
  standalone: true,
  templateUrl: './reporte-inventario.component.html',
  imports: [
    DatePipe, PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent, GsPipe,
    CardComponent, CardBodyComponent, RowComponent, ColComponent, NavComponent, NavItemComponent,
    NavLinkDirective, TableDirective, ProgressComponent, BadgeComponent, FormLabelDirective,
    FormSelectDirective, FormControlDirective,ExportarReporteComponent,
  ],
  styles: [`.tabla-scroll { max-height: 520px; overflow: auto; } thead th { position: sticky; top: 0; z-index: 1; }`],
})
export class ReporteInventarioComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaInventario; etiqueta: string }[] = [
    { clave: 'valorizado', etiqueta: 'Stock valorizado' },
    { clave: 'critico', etiqueta: 'Stock crítico' },
    { clave: 'sin-movimiento', etiqueta: 'Sin movimiento' },
    { clave: 'margen', etiqueta: 'Margen por producto' },
  ];

  readonly vista = signal<VistaInventario>('valorizado');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);

  private filtrosBase: FiltrosReporte | null = null;
  readonly categorias = signal<OpcionCategoria[]>([]);
  readonly marcas = signal<OpcionSimple[]>([]);
  readonly extra = signal<FiltrosInventarioExtra>({ categoria_id: null, marca_id: null, dias: 90 });
  readonly ultimosFiltros = signal<object | null>(null);

  private peticion?: Subscription;

  readonly cargando = computed(() => this.resultado().estado === 'cargando');
  readonly numero = formatoNumero;

  ngOnInit(): void {
    this.reportes.getConfig()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (c) => {
          this.categorias.set(c.categorias ?? []);
          this.marcas.set(c.marcas ?? []);
        },
      });
  }

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaInventario): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cambiarExtra(campo: keyof FiltrosInventarioExtra, valor: string): void {
    const v = campo === 'dias' ? (Number(valor) || 90) : (valor ? Number(valor) : null);
    this.extra.update((e) => ({ ...e, [campo]: v }));
  }

    cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const e = this.extra();
    const f: FiltrosReporte & FiltrosInventarioExtra = {
      ...this.filtrosBase,
      categoria_id: e.categoria_id,
      marca_id: e.marca_id,
      ...(this.vista() === 'sin-movimiento' ? { dias: e.dias } : {}),
    };

    const peticiones: Record<VistaInventario, () => Observable<RespuestaTabla<any, any>>> = {
      'valorizado': () => this.reportes.inventarioValorizado(f),
      'critico': () => this.reportes.inventarioCritico(f),
      'sin-movimiento': () => this.reportes.inventarioSinMovimiento(f),
      'margen': () => this.reportes.inventarioMargen(f),
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

  sangria(nivel: number): string {
    return '\u00A0\u00A0'.repeat(Math.max(0, nivel - 1));
  }

  claseMargen(pct: number | null): string {
    if (pct === null) {
      return 'text-body-secondary';
    }
    return pct < 0 ? 'text-danger fw-semibold' : pct < 10 ? 'text-warning' : 'text-success';
  }

  mensajeVacio(): string {
    return {
      'valorizado': 'No hay existencias para los filtros seleccionados.',
      'critico': 'Ningún producto está por debajo de su stock mínimo.',
      'sin-movimiento': 'Todos los productos con existencia tuvieron salidas en el período indicado.',
      'margen': 'No hay productos con precio y costo para comparar.',
    }[this.vista()];
  }
}
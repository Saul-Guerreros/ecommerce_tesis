import { Component, computed, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { Observable, Subscription } from 'rxjs';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import {
  CardBodyComponent, CardComponent, ColComponent, FormLabelDirective, FormSelectDirective,
  NavComponent, NavItemComponent, NavLinkDirective, ProgressComponent, RowComponent, TableDirective,
} from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';
import { ExportarReporteComponent } from '../shared/exportar-reporte/exportar-reporte.component';
import { ReportesService } from '../service/reportes.service';
import {
  EstadoCarga, FiltrosReporte, OpcionCategoria, OpcionSimple, RespuestaTabla,
} from '../models/reporte.model';
import { FiltrosVentasExtra, VistaVentas } from '../models/ventas.model';
import { FiltrosReporteComponent } from '../shared/filtros-reporte/filtros-reporte.component';
import { PaginaReporteComponent } from '../shared/pagina-reporte/pagina-reporte.component';
import { EstadoBloqueComponent } from '../shared/estado-bloque/estado-bloque.component';
import { GsPipe } from '../shared/gs.pipe';
import { formatoGs, formatoGsCompacto, formatoNumero } from '../shared/formato';

interface Resultado {
  estado: EstadoCarga;
  filas: any[];
  totales: Record<string, number | null> | null;
}

/**
 * Reporte de ventas con 5 vistas (pestañas). Los filtros comunes vienen del
 * componente de filtros; los propios (categoría, marca, agrupar por) se
 * manejan acá y se combinan al consultar.
 */
@Component({
  selector: 'app-reporte-ventas',
  standalone: true,
  templateUrl: './reporte-ventas.component.html',
  imports: [
    PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent, GsPipe,
    CardComponent, CardBodyComponent, RowComponent, ColComponent, NavComponent, NavItemComponent,
    NavLinkDirective, TableDirective, ProgressComponent, FormLabelDirective, FormSelectDirective,
    ChartjsComponent,ExportarReporteComponent,
  ],
  styles: [`.tabla-scroll { max-height: 520px; overflow: auto; } thead th { position: sticky; top: 0; z-index: 1; }`],
})
export class ReporteVentasComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaVentas; etiqueta: string }[] = [
    { clave: 'periodo', etiqueta: 'Por período' },
    { clave: 'productos', etiqueta: 'Por producto' },
    { clave: 'agrupado', etiqueta: 'Por categoría / marca' },
    { clave: 'metodos-pago', etiqueta: 'Por método de pago' },
    { clave: 'ubicacion', etiqueta: 'Por ciudad' },
  ];

  readonly vista = signal<VistaVentas>('periodo');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);

  // Filtros
  private filtrosBase: FiltrosReporte | null = null;
  readonly categorias = signal<OpcionCategoria[]>([]);
  readonly marcas = signal<OpcionSimple[]>([]);
  readonly extra = signal<FiltrosVentasExtra>({ categoria_id: null, marca_id: null, por: 'categoria' });

  /** Filtros de la última consulta: se usan para exportar exactamente lo que se ve. */
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

  // ================= Acciones =================

  /** Evento (aplicar) del componente de filtros. */
  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaVentas): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cambiarExtra(campo: keyof FiltrosVentasExtra, valor: string): void {
    const v = campo === 'por' ? valor : (valor ? Number(valor) : null);
    this.extra.update((e) => ({ ...e, [campo]: v }));
  }

  cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const f = this.filtrosBase;
    const e = this.extra();

    const params: Record<VistaVentas, FiltrosReporte & FiltrosVentasExtra> = {
      'periodo': f,
      'productos': { ...f, categoria_id: e.categoria_id, marca_id: e.marca_id },
      'agrupado': { ...f, por: e.por },
      'metodos-pago': f,
      'ubicacion': f,
    };

    const peticiones: Record<VistaVentas, (p: FiltrosReporte & FiltrosVentasExtra) => Observable<RespuestaTabla<any, any>>> = {
      'periodo': (p) => this.reportes.ventasPeriodo(p),
      'productos': (p) => this.reportes.ventasProductos(p),
      'agrupado': (p) => this.reportes.ventasAgrupado(p),
      'metodos-pago': (p) => this.reportes.ventasMetodosPago(p),
      'ubicacion': (p) => this.reportes.ventasUbicacion(p),
    };

    const vista = this.vista();
    const p = params[vista];

    this.peticion?.unsubscribe();
    this.errores.set(null);
    this.ultimosFiltros.set(p);
    this.resultado.update((r) => ({ ...r, estado: 'cargando' }));

    this.peticion = peticiones[vista](p)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => this.resultado.set({
          estado: this.esVacio(r.data) ? 'vacio' : 'ok',
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

  // ================= Gráficos =================

  readonly graficoPeriodo = computed<ChartData<'bar'>>(() => {
    const filas = this.vista() === 'periodo' ? this.resultado().filas : [];
    return {
      labels: filas.map((f) => f.etiqueta),
      datasets: [{
        label: 'Ventas',
        data: filas.map((f) => f.total),
        backgroundColor: getStyle('--cui-success') || '#2eb85c',
        borderRadius: 4,
      }],
    };
  });

  readonly opcionesPeriodo: ChartOptions<'bar'> = {
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: (ctx) => formatoGs(ctx.parsed.y) } },
    },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, ticks: { callback: (v) => formatoGsCompacto(Number(v)) } },
    },
  };

  readonly graficoMetodos = computed<ChartData<'doughnut'>>(() => {
    const filas = this.vista() === 'metodos-pago' ? this.resultado().filas : [];
    const paleta = ['success', 'info', 'warning', 'primary', 'danger', 'secondary']
      .map((c) => getStyle(`--cui-${c}`) || '#999');

    return {
      labels: filas.map((f) => f.metodo),
      datasets: [{ data: filas.map((f) => f.total), backgroundColor: filas.map((_, i) => paleta[i % paleta.length]) }],
    };
  });

  readonly opcionesMetodos: ChartOptions<'doughnut'> = {
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' },
      tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatoGs(ctx.parsed)}` } },
    },
  };

  // ================= Auxiliares =================

  /** Sangría por nivel para mostrar el árbol de categorías en el select. */
  sangria(nivel: number): string {
    return '\u00A0\u00A0'.repeat(Math.max(0, nivel - 1));
  }

  claseMargen(pct: number | null): string {
    if (pct === null) {
      return 'text-body-secondary';
    }
    return pct < 0 ? 'text-danger fw-semibold' : pct < 10 ? 'text-warning' : 'text-success';
  }

  private esVacio(filas: any[]): boolean {
    if (this.vista() === 'periodo') {
      return filas.every((f) => f.facturas === 0);
    }
    return filas.length === 0;
  }
}
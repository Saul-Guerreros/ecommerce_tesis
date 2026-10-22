import { DatePipe } from '@angular/common';
import {
  Component, computed, DestroyRef, DOCUMENT, inject, OnInit, Renderer2, signal, WritableSignal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { Observable, Subscription } from 'rxjs';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import {
  BadgeComponent, ButtonDirective, ButtonGroupComponent, CardBodyComponent, CardComponent,
  CardFooterComponent, CardHeaderComponent, ColComponent, FormSelectDirective, ProgressComponent,
  RowComponent, TableDirective, TemplateIdDirective, WidgetStatAComponent,
} from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';
import { IconDirective } from '@coreui/icons-angular';

import { AuthService } from '../../../auth/services/auth.service';
import { ReportesService } from '../../reportes/service/reportes.service';
import {
  Agrupacion, Bloque, EstadoCarga, FiltrosReporte, OpcionSucursal, RespuestaReporte,
} from '../../reportes/models/reporte.model';
import {
  DashboardDistribucion, DashboardResumen, DashboardTablas, DashboardVentasCompras,
} from '../../reportes/models/dashboard.model';
import { EstadoBloqueComponent } from '../../reportes/shared/estado-bloque/estado-bloque.component';
import { GsPipe } from '../../reportes/shared/gs.pipe';
import { formatoGs, formatoGsCompacto, formatoNumero, formatoVariacion } from '../../reportes/shared/formato';

type ClaveBloque = 'resumen' | 'ventasCompras' | 'distribucion' | 'tablas';

const BLOQUE_INICIAL = { estado: 'cargando' as EstadoCarga, data: null };

/**
 * Dashboard del panel administrativo con datos reales.
 *
 * Cada bloque (widgets, gráfico principal, distribución, tablas) se carga
 * con su propio endpoint y tiene su propio estado: si uno falla, los demás
 * se siguen mostrando y el bloque fallido ofrece "Reintentar".
 */
@Component({
  selector: 'app-panel-dashboard',
  standalone: true,
  templateUrl: './panel-dashboard.component.html',
  imports: [
    DatePipe, RouterLink, GsPipe, EstadoBloqueComponent,
    RowComponent, ColComponent, CardComponent, CardHeaderComponent, CardBodyComponent,
    CardFooterComponent, WidgetStatAComponent, TemplateIdDirective, ButtonDirective,
    ButtonGroupComponent, FormSelectDirective, ProgressComponent, TableDirective,
    BadgeComponent, ChartjsComponent, IconDirective,
  ],
  styles: [`
    .lista-progreso .fila + .fila { margin-top: .85rem; }
    .tabla-scroll { max-height: 360px; overflow-y: auto; }
  `],
})
export class PanelDashboardComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly auth = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly document = inject(DOCUMENT);
  private readonly renderer = inject(Renderer2);

  /** La seguridad real está en el backend; esto solo evita llamadas que darían 403. */
  readonly puedeVer = this.auth.tieneRol('Administrador') || this.auth.tienePermiso('metricas.dashboard.ver');

  readonly agrupaciones: { valor: Agrupacion; etiqueta: string }[] = [
    { valor: 'dia', etiqueta: 'Día' },
    { valor: 'mes', etiqueta: 'Mes' },
    { valor: 'anio', etiqueta: 'Año' },
  ];

  // ---------- Filtros ----------
  readonly sucursales = signal<OpcionSucursal[]>([]);
  readonly sucursalId = signal<number | null>(null);
  readonly agrupacion = signal<Agrupacion>('mes');

  // ---------- Bloques ----------
  readonly resumen = signal<Bloque<DashboardResumen>>({ ...BLOQUE_INICIAL });
  readonly ventasCompras = signal<Bloque<DashboardVentasCompras>>({ ...BLOQUE_INICIAL });
  readonly distribucion = signal<Bloque<DashboardDistribucion>>({ ...BLOQUE_INICIAL });
  readonly tablas = signal<Bloque<DashboardTablas>>({ ...BLOQUE_INICIAL });

  /** Petición en curso por bloque: se cancela si cambia el filtro antes de responder. */
  private readonly peticiones: Partial<Record<ClaveBloque, Subscription>> = {};

  /** Se incrementa al cambiar modo claro/oscuro para recalcular colores de gráficos. */
  private readonly tema = signal(0);

  // ================= Ciclo de vida =================

  ngOnInit(): void {
    if (!this.puedeVer) {
      return;
    }

    this.escucharCambioDeTema();

    this.reportes.getConfig()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (config) => this.sucursales.set(config.sucursales),
        error: () => this.sucursales.set([]),
      });

    this.cargarTodo();
  }

  // ================= Acciones =================

  cambiarSucursal(valor: string): void {
    this.sucursalId.set(valor ? Number(valor) : null);
    this.cargarTodo();
  }

  cambiarAgrupacion(valor: Agrupacion): void {
    if (valor === this.agrupacion()) {
      return;
    }
    this.agrupacion.set(valor);
    this.cargarVentasCompras();
  }

  cargarTodo(): void {
    this.cargarResumen();
    this.cargarVentasCompras();
    this.cargarDistribucion();
    this.cargarTablas();
  }

  cargarResumen(): void {
    this.cargar('resumen', this.resumen, this.reportes.dashboardResumen(this.filtros()));
  }

  cargarVentasCompras(): void {
    this.cargar(
      'ventasCompras',
      this.ventasCompras,
      this.reportes.dashboardVentasCompras({ ...this.filtros(), agrupacion: this.agrupacion() }),
      (d) => d.serie.every((p) => p.ventas === 0 && p.compras === 0),
    );
  }

  cargarDistribucion(): void {
    this.cargar('distribucion', this.distribucion, this.reportes.dashboardDistribucion(this.filtros()));
  }

  cargarTablas(): void {
    this.cargar('tablas', this.tablas, this.reportes.dashboardTablas(this.filtros()));
  }

  // ================= Gráficos (computed: se recalculan solos al cambiar datos o tema) =================

  readonly graficoDiario = computed<ChartData<'line'>>(() => {
    this.tema();
    const diario = this.resumen().data?.ventas.diario ?? [];

    return {
      labels: diario.map((d) => d.fecha.slice(8, 10)),
      datasets: [{
        label: 'Ventas',
        data: diario.map((d) => d.total),
        backgroundColor: 'transparent',
        borderColor: 'rgba(255,255,255,.55)',
        pointBackgroundColor: this.color('primary', '#5856d6'),
      }],
    };
  });

  readonly opcionesDiario: ChartOptions<'line'> = {
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: (ctx) => formatoGs(ctx.parsed.y) } },
    },
    scales: { x: { display: false }, y: { display: false } },
    elements: {
      line: { borderWidth: 2, tension: 0.4 },
      point: { radius: 0, hitRadius: 10, hoverRadius: 4 },
    },
  };

  readonly graficoPrincipal = computed<ChartData<'bar'>>(() => {
    this.tema();
    const serie = this.ventasCompras().data?.serie ?? [];

    return {
      labels: serie.map((p) => p.etiqueta),
      datasets: [
        { label: 'Ventas', data: serie.map((p) => p.ventas), backgroundColor: this.color('success', '#2eb85c'), borderRadius: 4 },
        { label: 'Compras', data: serie.map((p) => p.compras), backgroundColor: this.color('info', '#3399ff'), borderRadius: 4 },
      ],
    };
  });

  readonly opcionesPrincipal = computed<ChartOptions<'bar'>>(() => this.opcionesBarras(false));

  readonly graficoDiaSemana = computed<ChartData<'bar'>>(() => {
    this.tema();
    const dias = this.distribucion().data?.dia_semana ?? [];

    return {
      labels: dias.map((d) => d.etiqueta),
      datasets: [
        { label: 'Clientes nuevos', data: dias.map((d) => d.nuevos), backgroundColor: this.color('primary', '#5856d6'), borderRadius: 4 },
        { label: 'Clientes recurrentes', data: dias.map((d) => d.recurrentes), backgroundColor: this.color('info', '#3399ff'), borderRadius: 4 },
      ],
    };
  });

  readonly opcionesDiaSemana = computed<ChartOptions<'bar'>>(() => this.opcionesBarras(true));

  readonly graficoMetodosPago = computed<ChartData<'doughnut'>>(() => {
    this.tema();
    const metodos = this.distribucion().data?.metodos_pago ?? [];
    const paleta = ['success', 'info', 'warning', 'primary', 'danger', 'secondary']
      .map((c) => this.color(c, '#999'));

    return {
      labels: metodos.map((m) => m.metodo),
      datasets: [{
        data: metodos.map((m) => m.total),
        backgroundColor: metodos.map((_, i) => paleta[i % paleta.length]),
      }],
    };
  });

  readonly opcionesMetodosPago = computed<ChartOptions<'doughnut'>>(() => {
    this.tema();
    return {
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { color: this.color('body-color', '#6c757d') } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatoGs(ctx.parsed)}` } },
      },
    };
  });

  // ================= Valores derivados para la vista =================

  readonly maxCategoria = computed(() =>
    Math.max(1, ...(this.distribucion().data?.categorias ?? []).map((c) => c.total)));

  readonly totalDepartamentos = computed(() =>
    (this.distribucion().data?.departamentos ?? []).reduce((s, d) => s + d.total, 0) || 1);

  /** Proporción de compras sobre ventas, para la barra del pie del gráfico. */
  readonly ratioCompras = computed(() => {
    const t = this.ventasCompras().data?.totales;
    return t && t.ventas > 0 ? Math.min(100, (t.compras / t.ventas) * 100) : 0;
  });

  // ================= Auxiliares de plantilla =================

  readonly variacion = formatoVariacion;
  readonly numero = formatoNumero;

  /** Estado de una sub-lista dentro de un bloque ya cargado. */
  estadoLista(bloque: Bloque<unknown>, cantidad: number): EstadoCarga {
    return bloque.estado !== 'ok' ? bloque.estado : (cantidad > 0 ? 'ok' : 'vacio');
  }

  diaSemanaVacio(): boolean {
    return (this.distribucion().data?.dia_semana ?? []).every((d) => d.nuevos === 0 && d.recurrentes === 0);
  }

  colorEstadoPedido(codigo: string | null): string {
    return ({
      PENDIENTE_PEDIDO: 'secondary', EN_PREPARACION: 'info', LISTO: 'primary',
      ENTREGADO: 'success', CANCELADO: 'danger',
    } as Record<string, string>)[codigo ?? ''] ?? 'secondary';
  }

  colorEstadoPago(codigo: string | null): string {
    return ({
      PENDIENTE_PAGO: 'warning', PAGO_VERIFICAR: 'info', PARCIAL: 'warning',
      CONFIRMADO: 'success', RECHAZADO: 'danger',
    } as Record<string, string>)[codigo ?? ''] ?? 'secondary';
  }

  /** "2026-10-21 14:30:00" → ISO, para que DatePipe lo interprete en todos los navegadores. */
  fecha(valor: string): string {
    return valor.replace(' ', 'T');
  }

  // ================= Privados =================

  private filtros(): FiltrosReporte {
    return { sucursal_id: this.sucursalId() };
  }

  /**
   * Carga genérica de un bloque: conserva el dato anterior mientras carga
   * (evita parpadeos) y cancela la petición previa del mismo bloque, para
   * que una respuesta vieja no pise a una más nueva.
   */
  private cargar<T>(
    clave: ClaveBloque,
    destino: WritableSignal<Bloque<T>>,
    peticion: Observable<RespuestaReporte<T>>,
    esVacio: (data: T) => boolean = () => false,
  ): void {
    this.peticiones[clave]?.unsubscribe();
    destino.update((b) => ({ ...b, estado: 'cargando' }));

    this.peticiones[clave] = peticion
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => destino.set({ estado: esVacio(r.data) ? 'vacio' : 'ok', data: r.data }),
        error: () => destino.set({ estado: 'error', data: null }),
      });
  }

  private opcionesBarras(apiladas: boolean): ChartOptions<'bar'> {
    this.tema();
    const texto = this.color('body-color', '#6c757d');
    const grilla = this.color('border-color-translucent', 'rgba(0,0,0,.1)');

    return {
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: texto } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatoGs(ctx.parsed.y)}` } },
      },
      scales: {
        x: { stacked: apiladas, grid: { display: false }, ticks: { color: texto } },
        y: {
          stacked: apiladas,
          beginAtZero: true,
          grid: { color: grilla },
          ticks: { color: texto, callback: (v) => formatoGsCompacto(Number(v)) },
        },
      },
    };
  }

  private color(variable: string, respaldo: string): string {
    return getStyle(`--cui-${variable}`) || respaldo;
  }

    private escucharCambioDeTema(): void {
    const quitar = this.renderer.listen(this.document.documentElement, 'ColorSchemeChange', () => {
      setTimeout(() => this.tema.update((v) => v + 1));
    });

    this.destroyRef.onDestroy(() => quitar());
  }
}
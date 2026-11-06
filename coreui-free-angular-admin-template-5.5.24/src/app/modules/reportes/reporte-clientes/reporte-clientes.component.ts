import { DatePipe } from '@angular/common';
import { Component, computed, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { Observable, Subscription } from 'rxjs';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import {
  BadgeComponent, CardBodyComponent, CardComponent, ColComponent, FormControlDirective,
  FormLabelDirective, FormSelectDirective, NavComponent, NavItemComponent, NavLinkDirective,
  ProgressComponent, RowComponent, TableDirective,
} from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';

import { ReportesService } from '../service/reportes.service';
import { EstadoCarga, FiltrosReporte, OpcionSimple, RespuestaTabla } from '../models/reporte.model';
import { FiltrosClientesExtra, VistaClientes } from '../models/clientes.model';
import { FiltrosReporteComponent } from '../shared/filtros-reporte/filtros-reporte.component';
import { PaginaReporteComponent } from '../shared/pagina-reporte/pagina-reporte.component';
import { EstadoBloqueComponent } from '../shared/estado-bloque/estado-bloque.component';
import { ExportarReporteComponent } from '../shared/exportar-reporte/exportar-reporte.component';
import { GsPipe } from '../shared/gs.pipe';
import { formatoNumero } from '../shared/formato';

interface Resultado {
  estado: EstadoCarga;
  filas: any[];
  totales: Record<string, any> | null;
}

/**
 * Reporte de clientes: ranking por compras, altas y compradores nuevos por
 * período, e inactivos (foto actual, con datos de contacto para reactivación).
 */
@Component({
  selector: 'app-reporte-clientes',
  standalone: true,
  templateUrl: './reporte-clientes.component.html',
  imports: [
    DatePipe, PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent,
    ExportarReporteComponent, GsPipe, CardComponent, CardBodyComponent, RowComponent, ColComponent,
    NavComponent, NavItemComponent, NavLinkDirective, TableDirective, ProgressComponent, BadgeComponent,
    FormLabelDirective, FormSelectDirective, FormControlDirective, ChartjsComponent,
  ],
  styles: [`.tabla-scroll { max-height: 520px; overflow: auto; } thead th { position: sticky; top: 0; z-index: 1; }`],
})
export class ReporteClientesComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaClientes; etiqueta: string }[] = [
    { clave: 'top', etiqueta: 'Top clientes' },
    { clave: 'nuevos', etiqueta: 'Clientes nuevos' },
    { clave: 'inactivos', etiqueta: 'Clientes inactivos' },
  ];

  readonly limites = [10, 20, 50, 100];

  readonly vista = signal<VistaClientes>('top');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);

  private filtrosBase: FiltrosReporte | null = null;
  readonly tiposCliente = signal<OpcionSimple[]>([]);
  readonly extra = signal<FiltrosClientesExtra>({ tipo_cliente_id: null, limite: 20, dias: 90 });
  readonly ultimosFiltros = signal<object | null>(null);

  private peticion?: Subscription;

  readonly cargando = computed(() => this.resultado().estado === 'cargando');
  /** "Inactivos" es una foto al día de hoy: no usa rango de fechas. */
  readonly usaFechas = computed(() => this.vista() !== 'inactivos');
  readonly numero = formatoNumero;

  ngOnInit(): void {
    this.reportes.getConfig()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (c) => this.tiposCliente.set(c.tipos_cliente ?? []) });
  }

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaClientes): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cambiarExtra(campo: keyof FiltrosClientesExtra, valor: string): void {
    const v = campo === 'tipo_cliente_id' ? (valor ? Number(valor) : null) : (Number(valor) || undefined);
    this.extra.update((e) => ({ ...e, [campo]: v }));
  }

  cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const e = this.extra();
    const vista = this.vista();

    const params: Record<VistaClientes, FiltrosReporte & FiltrosClientesExtra> = {
      'top': { ...this.filtrosBase, tipo_cliente_id: e.tipo_cliente_id, limite: e.limite },
      'nuevos': { ...this.filtrosBase },
      'inactivos': { ...this.filtrosBase, tipo_cliente_id: e.tipo_cliente_id, dias: e.dias },
    };

    const peticiones: Record<VistaClientes, (p: FiltrosReporte & FiltrosClientesExtra) => Observable<RespuestaTabla<any, any>>> = {
      'top': (p) => this.reportes.clientesTop(p),
      'nuevos': (p) => this.reportes.clientesNuevos(p),
      'inactivos': (p) => this.reportes.clientesInactivos(p),
    };

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

  // ================= Gráfico de clientes nuevos =================

  readonly graficoNuevos = computed<ChartData<'bar'>>(() => {
    const filas = this.vista() === 'nuevos' ? this.resultado().filas : [];
    const color = (c: string, r: string) => getStyle(`--cui-${c}`) || r;

    return {
      labels: filas.map((f) => f.etiqueta),
      datasets: [
        { label: 'Registrados', data: filas.map((f) => f.registrados), backgroundColor: color('secondary', '#9da5b1'), borderRadius: 4 },
        { label: 'Compradores nuevos', data: filas.map((f) => f.nuevos), backgroundColor: color('primary', '#5856d6'), borderRadius: 4 },
        { label: 'Recurrentes', data: filas.map((f) => f.recurrentes), backgroundColor: color('info', '#3399ff'), borderRadius: 4 },
      ],
    };
  });

  readonly opcionesNuevos: ChartOptions<'bar'> = {
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, ticks: { precision: 0 } },
    },
  };

  // ================= Auxiliares =================

  fecha(valor: string | null): string | null {
    return valor ? `${valor.substring(0, 10)}T00:00:00` : null;
  }

  colorTramo(indice: number): string {
    return ['warning', 'danger', 'dark'][indice] ?? 'secondary';
  }

  mensajeVacio(): string {
    return {
      'top': 'No hay compras de clientes en el período seleccionado.',
      'nuevos': 'No hay altas ni compras de clientes en el período seleccionado.',
      'inactivos': 'Ningún cliente supera el umbral de inactividad indicado.',
    }[this.vista()];
  }

  private esVacio(filas: any[]): boolean {
    if (this.vista() === 'nuevos') {
      return filas.every((f) => f.registrados === 0 && f.activos === 0);
    }
    return filas.length === 0;
  }
}

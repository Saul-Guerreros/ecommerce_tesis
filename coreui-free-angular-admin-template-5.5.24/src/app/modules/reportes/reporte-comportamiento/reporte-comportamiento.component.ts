import { DatePipe, NgTemplateOutlet } from '@angular/common';
import { Component, computed, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { debounceTime, distinctUntilChanged, forkJoin, Subject, Subscription, switchMap, of } from 'rxjs';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import {
  BadgeComponent, ButtonDirective, CardBodyComponent, CardComponent, CardHeaderComponent, ColComponent,
  FormControlDirective, FormLabelDirective, FormSelectDirective, ListGroupDirective, ListGroupItemDirective,
  NavComponent, NavItemComponent, NavLinkDirective, ProgressComponent, RowComponent, SpinnerComponent, TableDirective,
} from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';

import { ReportesService } from '../service/reportes.service';
import { Bloque, FiltrosReporte } from '../models/reporte.model';
import {
  CarritoAbandonado, CeldaFranja, ClienteEncontrado, ClienteSegmentado, EtapaEmbudo, FiltrosComportamientoExtra,
  InsightsIa, Modalidad, ParComprado, PerfilCliente, ProductoBuscado, TerminoBuscado, TerminoSinResultado,
  TotalesBusquedas, TotalesCanasta, TotalesCarritos, TotalesEmbudo, TotalesFranja, TotalesRecompra,
  TotalesSegmentos, TramoRecompra, VistaComportamiento,
} from '../models/comportamiento.model';
import { FiltrosReporteComponent } from '../shared/filtros-reporte/filtros-reporte.component';
import { PaginaReporteComponent } from '../shared/pagina-reporte/pagina-reporte.component';
import { EstadoBloqueComponent } from '../shared/estado-bloque/estado-bloque.component';
import { GsPipe } from '../shared/gs.pipe';
import { formatoNumero } from '../shared/formato';

interface DatosBusquedas {
  terminos: TerminoBuscado[]; totales: TotalesBusquedas;
  sinResultados: TerminoSinResultado[];
  productos: ProductoBuscado[];
}

interface DatosPatrones {
  franja: CeldaFranja[]; totFranja: TotalesFranja;
  juntos: ParComprado[]; totJuntos: TotalesCanasta;
  recompra: TramoRecompra[]; totRecompra: TotalesRecompra;
  modalidades: Modalidad[];
  embudo: EtapaEmbudo[]; totEmbudo: TotalesEmbudo;
  carritos: CarritoAbandonado[]; totCarritos: TotalesCarritos;
}

/**
 * Análisis de comportamiento de clientes: productos más buscados, patrones de
 * compra, segmentación RFM, perfil individual y recomendaciones con IA.
 * Cada pestaña pide sus bloques en paralelo (forkJoin) solo cuando se abre.
 */
@Component({
  selector: 'app-reporte-comportamiento',
  standalone: true,
  templateUrl: './reporte-comportamiento.component.html',
  imports: [
    DatePipe, NgTemplateOutlet, GsPipe, PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent, ChartjsComponent,
    CardComponent, CardBodyComponent, CardHeaderComponent, RowComponent, ColComponent, NavComponent, NavItemComponent,
    NavLinkDirective, TableDirective, BadgeComponent, ProgressComponent, ButtonDirective, SpinnerComponent,
    FormLabelDirective, FormSelectDirective, FormControlDirective, ListGroupDirective, ListGroupItemDirective,
  ],
  styles: [`
    .tabla-scroll { max-height: 420px; overflow: auto; }
    thead th { position: sticky; top: 0; z-index: 1; }
    .calor td { min-width: 30px; text-align: center; font-size: .75rem; padding: .3rem .15rem; }
    .kpi { font-size: 1.4rem; font-weight: 600; }
  `],
})
export class ReporteComportamientoComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  readonly vistas: { clave: VistaComportamiento; etiqueta: string }[] = [
    { clave: 'busquedas', etiqueta: 'Productos más buscados' },
    { clave: 'patrones', etiqueta: 'Patrones de compra' },
    { clave: 'segmentos', etiqueta: 'Segmentos de clientes' },
    { clave: 'cliente', etiqueta: 'Perfil por cliente' },
    { clave: 'ia', etiqueta: 'Recomendaciones IA' },
  ];
  readonly opcionesMeses = [3, 6, 12, 24];
  readonly diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  readonly horas = Array.from({ length: 24 }, (_, i) => i);
  readonly numero = formatoNumero;

  readonly vista = signal<VistaComportamiento>('busquedas');
  readonly extra = signal<FiltrosComportamientoExtra>({ origen: null, horas: 48, meses: 12, limite: 15 });
  readonly errores = signal<Record<string, string[]> | null>(null);

  readonly busquedas = signal<Bloque<DatosBusquedas>>({ estado: 'cargando', data: null });
  readonly patrones = signal<Bloque<DatosPatrones>>({ estado: 'cargando', data: null });
  readonly segmentos = signal<Bloque<{ filas: ClienteSegmentado[]; totales: TotalesSegmentos }>>({ estado: 'cargando', data: null });
  readonly filtroSegmento = signal<string>('');

  readonly resultadosCliente = signal<ClienteEncontrado[]>([]);
  readonly perfil = signal<Bloque<PerfilCliente>>({ estado: 'vacio', data: null });
  readonly clienteId = signal<number | null>(null);

  readonly ia = signal<{ estado: 'inicial' | 'cargando' | 'ok' | 'error'; data: InsightsIa | null; mensaje?: string }>({ estado: 'inicial', data: null });

  private filtrosBase: FiltrosReporte | null = null;
  private peticion?: Subscription;
  private readonly busquedaCliente$ = new Subject<string>();

  /** Segmentos y perfil usan "últimos N meses": necesitan historia, no un rango corto. */
  readonly usaFechas = computed(() => !['segmentos', 'cliente'].includes(this.vista()));
  readonly cargando = computed(() => {
    const v = this.vista();
    return (v === 'busquedas' && this.busquedas().estado === 'cargando')
      || (v === 'patrones' && this.patrones().estado === 'cargando')
      || (v === 'segmentos' && this.segmentos().estado === 'cargando');
  });

  ngOnInit(): void {
    this.busquedaCliente$
      .pipe(
        debounceTime(350),
        distinctUntilChanged(),
        switchMap((q) => (q.trim().length >= 2 ? this.reportes.compBuscarClientes(q.trim()) : of({ data: [] }))),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({ next: (r) => this.resultadosCliente.set(r.data), error: () => this.resultadosCliente.set([]) });

    // /reportes/comportamiento?cliente=15 abre directo el perfil (enlaces desde otras pantallas).
    const id = Number(this.route.snapshot.queryParamMap.get('cliente'));
    if (id) {
      this.vista.set('cliente');
      this.clienteId.set(id);
    }
  }

  // ================= Filtros y navegación =================

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.ia.set({ estado: 'inicial', data: null });
    this.cargar();
  }

  cambiarVista(vista: VistaComportamiento): void {
    if (vista !== this.vista()) {
      this.vista.set(vista);
      this.cargar();
    }
  }

  cambiarExtra(campo: keyof FiltrosComportamientoExtra, valor: string): void {
    const v = campo === 'origen' ? (valor || null) : Number(valor) || undefined;
    this.extra.update((e) => ({ ...e, [campo]: v }));
  }

  verCliente(id: number | null): void {
    if (!id) return;
    this.clienteId.set(id);
    this.resultadosCliente.set([]);
    this.vista.set('cliente');
    this.router.navigate([], { queryParams: { cliente: id }, replaceUrl: true });
    this.cargar();
  }

  buscarCliente(texto: string): void {
    this.busquedaCliente$.next(texto);
  }

  cargar(): void {
    if (!this.filtrosBase) return;

    this.peticion?.unsubscribe();
    this.errores.set(null);
    const f = { ...this.filtrosBase, ...this.extra() };

    switch (this.vista()) {
      case 'busquedas': return this.cargarBusquedas(f);
      case 'patrones': return this.cargarPatrones(f);
      case 'segmentos': return this.cargarSegmentos(f);
      case 'cliente': return this.cargarPerfil(f);
      case 'ia': return; // la IA se genera solo con el botón (consume cuota)
    }
  }

  private cargarBusquedas(f: FiltrosReporte & FiltrosComportamientoExtra): void {
    this.busquedas.set({ estado: 'cargando', data: this.busquedas().data });
    this.peticion = forkJoin({
      t: this.reportes.compBusquedas(f),
      s: this.reportes.compSinResultados(f),
      p: this.reportes.compProductosBuscados(f),
    }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: ({ t, s, p }) => this.busquedas.set({
        estado: t.data.length || p.data.length ? 'ok' : 'vacio',
        data: { terminos: t.data, totales: t.meta.totales, sinResultados: s.data, productos: p.data },
      }),
      error: (e) => this.fallo(e, this.busquedas),
    });
  }

  private cargarPatrones(f: FiltrosReporte & FiltrosComportamientoExtra): void {
    this.patrones.set({ estado: 'cargando', data: this.patrones().data });
    this.peticion = forkJoin({
      fr: this.reportes.compFranjaHoraria(f),
      cj: this.reportes.compCompradosJuntos(f),
      rc: this.reportes.compRecompra(f),
      md: this.reportes.compModalidades(f),
      em: this.reportes.compEmbudo(f),
      ca: this.reportes.compCarritosAbandonados(f),
    }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: ({ fr, cj, rc, md, em, ca }) => this.patrones.set({
        estado: fr.meta.totales.compras || em.data[0]?.cantidad || ca.data.length ? 'ok' : 'vacio',
        data: {
          franja: fr.data, totFranja: fr.meta.totales, juntos: cj.data, totJuntos: cj.meta.totales,
          recompra: rc.data, totRecompra: rc.meta.totales, modalidades: md.data,
          embudo: em.data, totEmbudo: em.meta.totales, carritos: ca.data, totCarritos: ca.meta.totales,
        },
      }),
      error: (e) => this.fallo(e, this.patrones),
    });
  }

  private cargarSegmentos(f: FiltrosReporte & FiltrosComportamientoExtra): void {
    this.segmentos.set({ estado: 'cargando', data: this.segmentos().data });
    this.peticion = this.reportes.compSegmentos(f).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (r) => this.segmentos.set({ estado: r.data.length ? 'ok' : 'vacio', data: { filas: r.data, totales: r.meta.totales } }),
      error: (e) => this.fallo(e, this.segmentos),
    });
  }

  private cargarPerfil(f: FiltrosReporte & FiltrosComportamientoExtra): void {
    const id = this.clienteId();
    if (!id) {
      this.perfil.set({ estado: 'vacio', data: null });
      return;
    }
    this.perfil.set({ estado: 'cargando', data: null });
    this.ia.set({ estado: 'inicial', data: null });
    this.peticion = this.reportes.compPerfilCliente(id, f).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (r) => this.perfil.set({ estado: 'ok', data: r.data }),
      error: (e) => this.fallo(e, this.perfil),
    });
  }

  generarIa(forzar = false): void {
    if (!this.filtrosBase) return;

    const enPerfil = this.vista() === 'cliente';
    this.ia.set({ estado: 'cargando', data: null });
    this.reportes.compInsights({
      ...this.filtrosBase, ...this.extra(), forzar,
      cliente_id: enPerfil ? this.clienteId() : null,
    }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (r) => this.ia.set({ estado: 'ok', data: r.data }),
      error: (e: HttpErrorResponse) => this.ia.set({
        estado: 'error', data: null, mensaje: e.error?.message ?? 'No se pudo generar el análisis.',
      }),
    });
  }

  private fallo(err: HttpErrorResponse, destino: { set: (v: any) => void }): void {
    if (err.status === 422) this.errores.set(err.error?.errors ?? null);
    destino.set({ estado: 'error', data: null });
  }

  // ================= Gráficos =================

  private color(nombre: string, respaldo: string): string {
    return getStyle(`--cui-${nombre}`) || respaldo;
  }

  readonly graficoTerminos = computed<ChartData<'bar'>>(() => {
    const filas = this.busquedas().data?.terminos ?? [];
    return {
      labels: filas.map((t) => t.termino),
      datasets: [
        { label: 'Búsquedas', data: filas.map((t) => t.veces), backgroundColor: this.color('primary', '#5856d6'), borderRadius: 4 },
        { label: 'Sin resultado', data: filas.map((t) => t.sin_resultado), backgroundColor: this.color('danger', '#e55353'), borderRadius: 4 },
      ],
    };
  });

  readonly opcionesBarraH: ChartOptions<'bar'> = {
    indexAxis: 'y', maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } },
  };

  readonly graficoSegmentos = computed<ChartData<'doughnut'>>(() => {
    const segs = (this.segmentos().data?.totales.segmentos ?? []).filter((s) => s.clientes > 0);
    return {
      labels: segs.map((s) => s.etiqueta),
      datasets: [{ data: segs.map((s) => s.clientes), backgroundColor: segs.map((s) => this.color(s.color, '#9da5b1')) }],
    };
  });

  readonly opcionesDona: ChartOptions<'doughnut'> = {
    maintainAspectRatio: false, plugins: { legend: { position: 'right' } },
  };

  readonly graficoRecompra = computed<ChartData<'bar'>>(() => {
    const filas = this.patrones().data?.recompra ?? [];
    return {
      labels: filas.map((t) => t.etiqueta),
      datasets: [{ label: 'Recompras', data: filas.map((t) => t.recompras), backgroundColor: this.color('info', '#3399ff'), borderRadius: 4 }],
    };
  });

  readonly opcionesBarra: ChartOptions<'bar'> = {
    maintainAspectRatio: false, plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
  };

  // ================= Mapa de calor =================

  /** Matriz [día 1..7][hora 0..23] con la cantidad de compras. */
  readonly matrizFranja = computed(() => {
    const m: number[][] = Array.from({ length: 7 }, () => Array(24).fill(0));
    for (const c of this.patrones().data?.franja ?? []) m[c.dia - 1][c.hora] = c.compras;
    return m;
  });

  fondoCelda(valor: number): string {
    const max = this.patrones().data?.totFranja.max_celda || 1;
    return valor ? `rgba(var(--cui-primary-rgb), ${(0.12 + 0.88 * valor / max).toFixed(2)})` : 'transparent';
  }

  // ================= Auxiliares de presentación =================

  readonly segmentosFiltrados = computed(() => {
    const filas = this.segmentos().data?.filas ?? [];
    const s = this.filtroSegmento();
    return s ? filas.filter((f) => f.segmento === s) : filas;
  });

  colorSegmento(clave: string): string {
    return this.segmentos().data?.totales.segmentos.find((s) => s.clave === clave)?.color ?? 'secondary';
  }

  etiquetaSegmento(clave: string): string {
    return this.segmentos().data?.totales.segmentos.find((s) => s.clave === clave)?.etiqueta ?? clave;
  }

  modalidadesDe(grupo: 'condicion' | 'entrega'): Modalidad[] {
    return (this.patrones().data?.modalidades ?? []).filter((m) => m.grupo === grupo);
  }

  pct(valor: number | null | undefined): string {
    return valor === null || valor === undefined ? '—' : `${formatoNumero(valor)} %`;
  }

  colorPrioridad(p: string): string {
    return ({ alta: 'danger', media: 'warning', baja: 'secondary' } as Record<string, string>)[p] ?? 'secondary';
  }

  fecha(valor: string | null | undefined): string | null {
    return valor ? valor.replace(' ', 'T') : null;
  }
}
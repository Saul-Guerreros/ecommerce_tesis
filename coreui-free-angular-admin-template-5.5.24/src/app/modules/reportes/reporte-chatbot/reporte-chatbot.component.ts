import { DatePipe } from '@angular/common';
import { Component, computed, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { Observable, Subscription } from 'rxjs';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import {
  BadgeComponent, CardBodyComponent, CardComponent, ColComponent, FormLabelDirective,
  FormSelectDirective, NavComponent, NavItemComponent, NavLinkDirective, ProgressComponent,
  RowComponent, TableDirective,
} from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';

import { ReportesService } from '../service/reportes.service';
import { EstadoCarga, FiltrosReporte, RespuestaTabla } from '../models/reporte.model';
import { FiltrosChatbotExtra, TipoNoResuelta, VistaChatbot } from '../models/chatbot.model';
import { FiltrosReporteComponent } from '../shared/filtros-reporte/filtros-reporte.component';
import { PaginaReporteComponent } from '../shared/pagina-reporte/pagina-reporte.component';
import { EstadoBloqueComponent } from '../shared/estado-bloque/estado-bloque.component';
import { ExportarReporteComponent } from '../shared/exportar-reporte/exportar-reporte.component';
import { formatoNumero } from '../shared/formato';

interface Resultado {
  estado: EstadoCarga;
  filas: any[];
  totales: Record<string, any> | null;
}

/**
 * Reporte del asistente virtual: uso por período, temas (intenciones),
 * preguntas frecuentes y detalle de consultas no resueltas para mejorar
 * el chatbot (catálogo, FAQ o prompt).
 */
@Component({
  selector: 'app-reporte-chatbot',
  standalone: true,
  templateUrl: './reporte-chatbot.component.html',
  imports: [
    DatePipe, PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent,
    ExportarReporteComponent, CardComponent, CardBodyComponent, RowComponent, ColComponent,
    NavComponent, NavItemComponent, NavLinkDirective, TableDirective, ProgressComponent, BadgeComponent,
    FormLabelDirective, FormSelectDirective, ChartjsComponent,
  ],
  styles: [`
    .tabla-scroll { max-height: 520px; overflow: auto; }
    thead th { position: sticky; top: 0; z-index: 1; }
    .texto-largo { max-width: 420px; white-space: normal; }
  `],
})
export class ReporteChatbotComponent {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaChatbot; etiqueta: string }[] = [
    { clave: 'periodo', etiqueta: 'Conversaciones' },
    { clave: 'temas', etiqueta: 'Temas consultados' },
    { clave: 'preguntas', etiqueta: 'Preguntas frecuentes' },
    { clave: 'sin-respuesta', etiqueta: 'Sin respuesta y derivadas' },
  ];

  readonly limites = [10, 20, 50, 100];

  readonly vista = signal<VistaChatbot>('periodo');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);
  readonly extra = signal<FiltrosChatbotExtra>({ limite: 20, tipo: 'todos' });
  readonly ultimosFiltros = signal<object | null>(null);

  private filtrosBase: FiltrosReporte | null = null;
  private peticion?: Subscription;

  readonly cargando = computed(() => this.resultado().estado === 'cargando');
  readonly numero = formatoNumero;

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaChatbot): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cambiarLimite(valor: string): void {
    this.extra.update((e) => ({ ...e, limite: Number(valor) || 20 }));
  }

  cambiarTipo(valor: string): void {
    this.extra.update((e) => ({ ...e, tipo: (valor || 'todos') as TipoNoResuelta }));
  }

  cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const e = this.extra();
    const vista = this.vista();

    const params: Record<VistaChatbot, FiltrosReporte & FiltrosChatbotExtra> = {
      'periodo': { ...this.filtrosBase },
      'temas': { ...this.filtrosBase },
      'preguntas': { ...this.filtrosBase, limite: e.limite },
      'sin-respuesta': { ...this.filtrosBase, tipo: e.tipo },
    };

    const peticiones: Record<VistaChatbot, (p: FiltrosReporte & FiltrosChatbotExtra) => Observable<RespuestaTabla<any, any>>> = {
      'periodo': (p) => this.reportes.chatbotPeriodo(p),
      'temas': (p) => this.reportes.chatbotTemas(p),
      'preguntas': (p) => this.reportes.chatbotPreguntas(p),
      'sin-respuesta': (p) => this.reportes.chatbotSinRespuesta(p),
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

  // ================= Gráfico de conversaciones =================

  readonly graficoPeriodo = computed<ChartData<'bar'>>(() => {
    const filas = this.vista() === 'periodo' ? this.resultado().filas : [];
    const color = (c: string, r: string) => getStyle(`--cui-${c}`) || r;

    return {
      labels: filas.map((f) => f.etiqueta),
      datasets: [
        { label: 'Conversaciones', data: filas.map((f) => f.conversaciones), backgroundColor: color('primary', '#5856d6'), borderRadius: 4 },
        { label: 'Derivadas', data: filas.map((f) => f.derivadas), backgroundColor: color('warning', '#f9b115'), borderRadius: 4 },
        { label: 'Con consultas sin respuesta', data: filas.map((f) => f.con_sin_respuesta), backgroundColor: color('danger', '#e55353'), borderRadius: 4 },
      ],
    };
  });

  readonly opcionesPeriodo: ChartOptions<'bar'> = {
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, ticks: { precision: 0 } },
    },
  };

  // ================= Auxiliares =================

  pct(v: number | null | undefined): string {
    return v === null || v === undefined ? '—' : `${formatoNumero(v)} %`;
  }

  latencia(ms: number | null | undefined): string {
    if (ms === null || ms === undefined) {
      return '—';
    }
    return ms >= 1000 ? `${formatoNumero(Math.round(ms / 100) / 10)} s` : `${ms} ms`;
  }

  fecha(valor: string | null): string | null {
    if (!valor) {
      return null;
    }
    return valor.length > 10 ? valor.replace(' ', 'T') : `${valor}T00:00:00`;
  }

  mensajeVacio(): string {
    return {
      'periodo': 'No hubo conversaciones con el asistente en el período.',
      'temas': 'El asistente no invocó herramientas en el período.',
      'preguntas': 'No hay mensajes de usuarios en el período.',
      'sin-respuesta': 'No hay consultas sin respuesta ni derivadas en el período.',
    }[this.vista()];
  }

  private esVacio(filas: any[]): boolean {
    if (this.vista() === 'periodo') {
      return filas.every((f) => f.conversaciones === 0 && f.mensajes === 0);
    }
    return filas.length === 0;
  }
}

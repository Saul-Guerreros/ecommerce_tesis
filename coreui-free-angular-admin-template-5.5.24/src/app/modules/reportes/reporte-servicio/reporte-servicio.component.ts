import { Component, computed, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpErrorResponse } from '@angular/common/http';
import { Observable, Subscription } from 'rxjs';
import {
  BadgeComponent, CardBodyComponent, CardComponent, ColComponent, FormLabelDirective,
  FormSelectDirective, NavComponent, NavItemComponent, NavLinkDirective, ProgressComponent,
  RowComponent, TableDirective,
} from '@coreui/angular';

import { ReportesService } from '../service/reportes.service';
import { EstadoCarga, FiltrosReporte, OpcionSimple, RespuestaTabla } from '../models/reporte.model';
import { FiltrosServicioExtra, VistaServicio } from '../models/servicio.model';
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
 * Reporte de servicio técnico: 5 vistas sobre solicitudes, órdenes,
 * diagnósticos y presupuestos. Filtro propio: tipo de dispositivo.
 */
@Component({
  selector: 'app-reporte-servicio',
  standalone: true,
  templateUrl: './reporte-servicio.component.html',
  imports: [
    PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent, ExportarReporteComponent,
    GsPipe, CardComponent, CardBodyComponent, RowComponent, ColComponent, NavComponent,
    NavItemComponent, NavLinkDirective, TableDirective, ProgressComponent, BadgeComponent,
    FormLabelDirective, FormSelectDirective,
  ],
  styles: [`.tabla-scroll { max-height: 520px; overflow: auto; } thead th { position: sticky; top: 0; z-index: 1; }`],
})
export class ReporteServicioComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaServicio; etiqueta: string; ayuda: string }[] = [
    { clave: 'estados', etiqueta: 'Por estado', ayuda: 'Solicitudes creadas en el período, según su estado actual.' },
    { clave: 'tecnicos', etiqueta: 'Por técnico', ayuda: 'Órdenes iniciadas en el período, agrupadas por técnico asignado.' },
    { clave: 'tiempos', etiqueta: 'Tiempos de reparación', ayuda: 'Órdenes cerradas en el período: horas de taller y días hasta la entrega.' },
    { clave: 'fallas', etiqueta: 'Fallas frecuentes', ayuda: 'Fallas registradas en los diagnósticos del período.' },
    { clave: 'presupuestos', etiqueta: 'Presupuestos', ayuda: 'Presupuestos creados en el período, por estado de respuesta del cliente.' },
    { clave: 'clasificacion', etiqueta: 'Clasificación IA', ayuda: 'Solicitudes creadas en el período según la clasificación automática (valores vigentes) y acierto de la IA sobre las revisadas.' },
  ];

   /** Punto 7: la vista de clasificación agrupa sus filas en tres dimensiones. */
  readonly dimensiones: { clave: 'categoria' | 'urgencia' | 'dispositivo'; titulo: string }[] = [
    { clave: 'categoria', titulo: 'Tipo de problema' },
    { clave: 'urgencia', titulo: 'Urgencia' },
    { clave: 'dispositivo', titulo: 'Tipo de dispositivo' },
  ];
  readonly vista = signal<VistaServicio>('estados');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);

  private filtrosBase: FiltrosReporte | null = null;
  readonly tiposDispositivo = signal<OpcionSimple[]>([]);
  readonly extra = signal<FiltrosServicioExtra>({ tipo_dispositivo_id: null });
  readonly ultimosFiltros = signal<object | null>(null);

  private peticion?: Subscription;

  readonly cargando = computed(() => this.resultado().estado === 'cargando');
  readonly ayuda = computed(() => this.vistas.find((v) => v.clave === this.vista())?.ayuda ?? '');
  readonly numero = formatoNumero;

  filasDe(dimension: string): any[] {
    return this.resultado().filas.filter((f) => f.dimension === dimension);
  }
  /** Máximo de la vista actual, para dibujar barras proporcionales. */
  readonly maximo = computed(() => {
    const campo = { estados: 'cantidad', tecnicos: 'asignadas', tiempos: 'horas_promedio', fallas: 'ocurrencias', presupuestos: 'cantidad', clasificacion: 'cantidad' }[this.vista()];
    return Math.max(1, ...this.resultado().filas.map((f) => Number(f[campo] ?? 0)));
  });

  ngOnInit(): void {
    this.reportes.getConfig()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (c) => this.tiposDispositivo.set(c.tipos_dispositivo ?? []) });
  }

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaServicio): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cambiarTipo(valor: string): void {
    this.extra.set({ tipo_dispositivo_id: valor ? Number(valor) : null });
  }

  cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const f: FiltrosReporte & FiltrosServicioExtra = { ...this.filtrosBase, ...this.extra() };

    const peticiones: Record<VistaServicio, () => Observable<RespuestaTabla<any, any>>> = {
      'estados': () => this.reportes.servicioEstados(f),
      'tecnicos': () => this.reportes.servicioTecnicos(f),
      'tiempos': () => this.reportes.servicioTiempos(f),
      'fallas': () => this.reportes.servicioFallas(f),
      'presupuestos': () => this.reportes.servicioPresupuestos(f),
      'clasificacion': () => this.reportes.servicioClasificacion(f),
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

  /** null → "—"; números con 1 decimal y sufijo opcional. */
  valor(v: number | null | undefined, sufijo = ''): string {
    return v === null || v === undefined ? '—' : `${formatoNumero(v)}${sufijo}`;
  }

  /** Horas → texto legible ("3,5 h" o "2,1 días" si supera 48 h). */
  duracion(horas: number | null): string {
    if (horas === null || horas === undefined) {
      return '—';
    }
    return horas >= 48 ? `${formatoNumero(Math.round((horas / 24) * 10) / 10)} días` : `${formatoNumero(horas)} h`;
  }

  colorEstadoPresupuesto(codigo: string): string {
    return ({
      APROBADO: 'success', RECHAZADO: 'danger', VENCIDO: 'secondary',
      ENVIADO: 'info', EN_REVISION: 'warning', BORRADOR: 'dark',
    } as Record<string, string>)[codigo] ?? 'secondary';
  }

  colorEfectividad(pct: number | null): string {
    if (pct === null) {
      return 'text-body-secondary';
    }
    return pct >= 80 ? 'text-success' : pct >= 50 ? 'text-warning' : 'text-danger';
  }

  mensajeVacio(): string {
    return {
      'estados': 'No hay solicitudes creadas en el período.',
      'tecnicos': 'No hay órdenes iniciadas en el período.',
      'tiempos': 'No hay órdenes cerradas en el período.',
      'fallas': 'No hay diagnósticos con fallas registradas en el período.',
      'presupuestos': 'No hay presupuestos creados en el período.',
      'clasificacion': 'No hay solicitudes clasificadas en el período.',
    }[this.vista()];
  }
}

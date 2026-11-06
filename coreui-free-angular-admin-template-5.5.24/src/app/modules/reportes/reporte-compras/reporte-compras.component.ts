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
import { EstadoCarga, FiltrosReporte, OpcionSimple, RespuestaTabla } from '../models/reporte.model';
import { FiltrosComprasExtra, VistaCompras } from '../models/compras.model';
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
 * Reporte de compras: por proveedor (con rango de fechas) y cuentas por
 * pagar vencidas / por vencer (fotos al día de hoy, sin rango de fechas).
 */
@Component({
  selector: 'app-reporte-compras',
  standalone: true,
  templateUrl: './reporte-compras.component.html',
  imports: [
    DatePipe, PaginaReporteComponent, FiltrosReporteComponent, EstadoBloqueComponent,
    ExportarReporteComponent, GsPipe, CardComponent, CardBodyComponent, RowComponent, ColComponent,
    NavComponent, NavItemComponent, NavLinkDirective, TableDirective, ProgressComponent,
    BadgeComponent, FormLabelDirective, FormSelectDirective, FormControlDirective,
  ],
  styles: [`.tabla-scroll { max-height: 520px; overflow: auto; } thead th { position: sticky; top: 0; z-index: 1; }`],
})
export class ReporteComprasComponent implements OnInit {
  private readonly reportes = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly vistas: { clave: VistaCompras; etiqueta: string }[] = [
    { clave: 'proveedores', etiqueta: 'Por proveedor' },
    { clave: 'cxp-vencidas', etiqueta: 'Cuentas por pagar vencidas' },
    { clave: 'cxp-por-vencer', etiqueta: 'Cuentas por pagar por vencer' },
  ];

  readonly vista = signal<VistaCompras>('proveedores');
  readonly resultado = signal<Resultado>({ estado: 'cargando', filas: [], totales: null });
  readonly errores = signal<Record<string, string[]> | null>(null);

  private filtrosBase: FiltrosReporte | null = null;
  readonly proveedores = signal<OpcionSimple[]>([]);
  readonly extra = signal<FiltrosComprasExtra>({ proveedor_id: null, dias: 30 });
  readonly ultimosFiltros = signal<object | null>(null);

  private peticion?: Subscription;

  readonly cargando = computed(() => this.resultado().estado === 'cargando');
  /** Solo "Por proveedor" usa rango de fechas; las CxP son fotos al día de hoy. */
  readonly usaFechas = computed(() => this.vista() === 'proveedores');
  readonly numero = formatoNumero;

  ngOnInit(): void {
    this.reportes.getConfig()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (c) => this.proveedores.set(c.proveedores ?? []) });
  }

  aplicarFiltros(filtros: FiltrosReporte): void {
    this.filtrosBase = filtros;
    this.cargar();
  }

  cambiarVista(vista: VistaCompras): void {
    if (vista === this.vista()) {
      return;
    }
    this.vista.set(vista);
    this.cargar();
  }

  cambiarExtra(campo: keyof FiltrosComprasExtra, valor: string): void {
    const v = campo === 'dias' ? (Number(valor) || 30) : (valor ? Number(valor) : null);
    this.extra.update((e) => ({ ...e, [campo]: v }));
  }

  cargar(): void {
    if (!this.filtrosBase) {
      return;
    }

    const e = this.extra();
    const vista = this.vista();
    const f: FiltrosReporte & FiltrosComprasExtra = {
      ...this.filtrosBase,
      proveedor_id: e.proveedor_id,
      ...(vista === 'cxp-por-vencer' ? { dias: e.dias } : {}),
    };

    const peticiones: Record<VistaCompras, () => Observable<RespuestaTabla<any, any>>> = {
      'proveedores': () => this.reportes.comprasProveedores(f),
      'cxp-vencidas': () => this.reportes.comprasVencidas(f),
      'cxp-por-vencer': () => this.reportes.comprasPorVencer(f),
    };

    this.peticion?.unsubscribe();
    this.errores.set(null);
    this.ultimosFiltros.set(f);
    this.resultado.update((r) => ({ ...r, estado: 'cargando' }));

    this.peticion = peticiones[vista]()
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

  /** "2026-10-21" → Date local (evita el corrimiento de zona horaria del DatePipe). */
  fecha(valor: string | null): string | null {
    return valor ? `${valor.substring(0, 10)}T00:00:00` : null;
  }

  colorTramo(indice: number, total: number): string {
    if (this.vista() === 'cxp-vencidas') {
      return ['warning', 'warning', 'danger', 'danger'][indice] ?? 'danger';
    }
    return indice <= 1 ? 'danger' : indice === total - 1 ? 'success' : 'warning';
  }

  mensajeVacio(): string {
    return {
      'proveedores': 'No hay compras confirmadas en el período seleccionado.',
      'cxp-vencidas': 'No hay cuotas vencidas pendientes de pago.',
      'cxp-por-vencer': 'No hay cuotas que venzan en el horizonte indicado.',
    }[this.vista()];
  }
}

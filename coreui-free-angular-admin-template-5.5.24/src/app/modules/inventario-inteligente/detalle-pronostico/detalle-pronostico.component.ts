// modules/inventario-inteligente/detalle-pronostico/detalle-pronostico.component.ts
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import { BadgeModule, ButtonModule, FormModule, GridModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';
import { GsPipe } from '../../reportes/shared/gs.pipe';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { NOMBRES_MODELO, PronosticoProducto, TIPOS_ALERTA } from '../models/inventario-inteligente.model';

/**
 * Detalle de un producto: ventas reales vs. pronóstico, cálculo de reposición
 * paso a paso, comparación de modelos y lead time propio.
 */
@Component({
  selector: 'app-detalle-pronostico',
  standalone: true,
  imports: [CommonModule, FormsModule, ModalModule, ButtonModule, BadgeModule, SpinnerModule, TableModule,
    FormModule, GridModule, ChartjsComponent, GsPipe],
  templateUrl: './detalle-pronostico.component.html',
})
export class DetallePronosticoComponent implements OnChanges {
  @Input() visible = false;
  @Input() productoId: number | null = null;
  @Input() depositoId: number | null = null;
  @Input() puedeGestionar = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() actualizado = new EventEmitter<void>();

  private service = inject(InventarioInteligenteService);
  private toastr = inject(ToastrService);

  readonly TIPOS = TIPOS_ALERTA;
  readonly MODELOS = NOMBRES_MODELO;
  /** Semanas de historial que se grafican (las últimas). */
  readonly semanasGrafico = 26;

  datos: PronosticoProducto | null = null;
  cargando = false;
  leadTime: number | null = null;
  grafico: ChartData<'line'> = { labels: [], datasets: [] };

  readonly opciones: ChartOptions<'line'> = {
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, title: { display: true, text: 'Unidades / semana' } } },
  };

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.productoId) {
      this.cargar();
    }
  }

  cargar(): void {
    this.cargando = true;
    this.datos = null;
    this.service.getPronostico(this.productoId!, this.depositoId ?? undefined).subscribe({
      next: (d) => {
        this.datos = d;
        this.leadTime = d.producto.lead_time;
        this.grafico = this.armarGrafico(d);
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudo cargar el pronóstico', 'Error'); this.cargando = false; },
    });
  }

  /**
   * Una sola serie de etiquetas (semanas) y dos datasets alineados:
   * real (null en el futuro) y pronóstico (null en el pasado, salvo la
   * última semana real para que la línea punteada arranque unida).
   */
  private armarGrafico(d: PronosticoProducto): ChartData<'line'> {
    const historial = d.historial.slice(-this.semanasGrafico);
    const etiquetas = [...historial, ...d.pronostico].map((p) => this.etiquetaSemana(p.semana));
    const color = (c: string, r: string) => getStyle(`--cui-${c}`) || r;

    const reales: (number | null)[] = [...historial.map((p) => p.unidades), ...d.pronostico.map(() => null)];
    const previstos: (number | null)[] = [
      ...historial.map((_, i) => (i === historial.length - 1 ? historial[i].unidades : null)),
      ...d.pronostico.map((p) => p.unidades),
    ];

    return {
      labels: etiquetas,
      datasets: [
        { label: 'Ventas reales', data: reales, borderColor: color('primary', '#5856d6'),
          backgroundColor: 'transparent', tension: 0.2, pointRadius: 2 },
        { label: 'Pronóstico', data: previstos, borderColor: color('danger', '#e55353'),
          backgroundColor: 'transparent', borderDash: [6, 4], tension: 0.2, pointRadius: 3 },
      ],
    };
  }

  private etiquetaSemana(fecha: string): string {
    const [, mes, dia] = fecha.substring(0, 10).split('-');
    return `${dia}/${mes}`;
  }

  guardarLeadTime(valor: number | null): void {
    if (valor !== null && (valor < 1 || valor > 180)) {
      this.toastr.warning('El lead time debe estar entre 1 y 180 días', 'Validación');
      return;
    }

    this.service.actualizarLeadTime(this.productoId!, valor).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Lead time actualizado', 'Éxito');
        this.cargar();
        this.actualizado.emit();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo actualizar', 'Error'),
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}

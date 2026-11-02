// modules/inventario-inteligente/metricas-modelos/metricas-modelos.component.ts
import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { ChartData, ChartOptions } from 'chart.js';
import { getStyle } from '@coreui/utils';
import { GridModule, SpinnerModule, TableModule } from '@coreui/angular';
import { ChartjsComponent } from '@coreui/angular-chartjs';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { MetricaModelo, NOMBRES_MODELO } from '../models/inventario-inteligente.model';

/** Desempeño global de los 5 modelos: cuántas veces ganó cada uno y su error promedio. */
@Component({
  selector: 'app-metricas-modelos',
  standalone: true,
  imports: [CommonModule, GridModule, SpinnerModule, TableModule, ChartjsComponent],
  templateUrl: './metricas-modelos.component.html',
})
export class MetricasModelosComponent implements OnInit {
  private service = inject(InventarioInteligenteService);
  private toastr = inject(ToastrService);

  readonly MODELOS = NOMBRES_MODELO;
  metricas: MetricaModelo[] = [];
  cargando = true;
  grafico: ChartData<'bar'> = { labels: [], datasets: [] };

  readonly opciones: ChartOptions<'bar'> = {
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
  };

  ngOnInit(): void {
    this.service.getMetricas().subscribe({
      next: (m) => {
        this.metricas = m;
        this.grafico = {
          labels: m.map((x) => this.MODELOS[x.modelo] ?? x.modelo),
          datasets: [{
            label: 'Productos donde fue el mejor modelo', data: m.map((x) => Number(x.veces_elegido)),
            backgroundColor: getStyle('--cui-primary') || '#5856d6', borderRadius: 4,
          }],
        };
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudieron cargar las métricas', 'Error'); this.cargando = false; },
    });
  }

  get totalElegidos(): number {
    return this.metricas.reduce((s, m) => s + Number(m.veces_elegido), 0);
  }
}

// modules/inventario-inteligente/insights-inventario/insights-inventario.component.ts
import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, SpinnerModule } from '@coreui/angular';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { InsightsInventario, PRIORIDADES_ACCION, TIPOS_ACCION } from '../models/inventario-inteligente.model';

/**
 * Análisis en lenguaje natural de las alertas (Gemini).
 * Se genera a pedido para no consumir la API en cada visita.
 */
@Component({
  selector: 'app-insights-inventario',
  standalone: true,
  imports: [CommonModule, CardModule, ButtonModule, BadgeModule, SpinnerModule],
  templateUrl: './insights-inventario.component.html',
})
export class InsightsInventarioComponent {
  private service = inject(InventarioInteligenteService);
  private toastr = inject(ToastrService);

  readonly PRIORIDADES = PRIORIDADES_ACCION;
  readonly TIPOS = TIPOS_ACCION;

  insights: InsightsInventario | null = null;
  cargando = false;
    /** Muestra u oculta el análisis ya generado. */
  expandido = true;

  generar(forzar = false): void {
    this.cargando = true;
    this.service.getInsights(forzar).subscribe({
      next: (r) => { this.insights = r; this.expandido = true; this.cargando = false; },
      error: (err) => {
        this.toastr.error(
          err?.status === 429 ? 'Demasiadas consultas. Esperá un minuto.' : (err?.error?.message ?? 'No se pudo generar el análisis'),
          'Análisis con IA',
        );
        this.cargando = false;
      },
    });
  }
  
}
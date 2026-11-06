// modules/inventario-inteligente/panel-inventario/panel-inventario.component.ts
import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, GridModule, NavModule, SpinnerModule } from '@coreui/angular';
import { AuthService } from '../../../auth/services/auth.service';
import { GsPipe } from '../../reportes/shared/gs.pipe';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { ResumenInventario, TIPOS_ALERTA, TipoAlerta } from '../models/inventario-inteligente.model';
import { ListAlertasComponent } from '../list-alertas/list-alertas.component';
import { MetricasModelosComponent } from '../metricas-modelos/metricas-modelos.component';
import { ConfigInventarioComponent } from '../config-inventario/config-inventario.component';
import { InsightsInventarioComponent } from '../insights-inventario/insights-inventario.component';

type Pestana = 'alertas' | 'metricas' | 'config';

/**
 * Pantalla principal: tarjetas de resumen + pestañas (alertas, métricas, configuración).
 * Al hacer clic en una tarjeta se filtra la lista por ese tipo de alerta.
 */
@Component({
  selector: 'app-panel-inventario',
  standalone: true,
  imports: [CommonModule, CardModule, GridModule, NavModule, ButtonModule, SpinnerModule, GsPipe,
      ListAlertasComponent, MetricasModelosComponent, ConfigInventarioComponent, InsightsInventarioComponent],
  templateUrl: './panel-inventario.component.html',
  styles: [`.tarjeta { cursor: pointer; transition: transform .1s; } .tarjeta:hover { transform: translateY(-2px); }
            .tarjeta.activa { outline: 2px solid var(--cui-primary); }`],
})
export class PanelInventarioComponent implements OnInit {
  private service = inject(InventarioInteligenteService);
  private toastr = inject(ToastrService);
  private auth = inject(AuthService);

  readonly tipos = Object.entries(TIPOS_ALERTA) as [TipoAlerta, (typeof TIPOS_ALERTA)[TipoAlerta]][];
  readonly puedeGestionar = this.auth.tienePermiso('inventario-inteligente.gestionar');

  resumen: ResumenInventario | null = null;
  pestana: Pestana = 'alertas';
  tipoSeleccionado: TipoAlerta | null = null;

  ngOnInit(): void {
    this.cargarResumen();
  }

  cargarResumen(): void {
    this.service.getResumen().subscribe({
      next: (r) => (this.resumen = r),
      error: () => this.toastr.error('No se pudo cargar el resumen', 'Error'),
    });
  }

  filtrarPorTipo(tipo: TipoAlerta): void {
    this.tipoSeleccionado = this.tipoSeleccionado === tipo ? null : tipo;
    this.pestana = 'alertas';
  }
}

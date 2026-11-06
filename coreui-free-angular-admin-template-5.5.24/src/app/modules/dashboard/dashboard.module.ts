import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { DashboardRoutingModule } from './dashboard-routing.module';
import { DashboardComponent } from './dashboard.component';
import { PanelDashboardComponent } from './panel-dashboard/panel-dashboard.component';

/** Componentes standalone: van en imports, no en declarations. */
@NgModule({
  declarations: [],
  imports: [CommonModule, DashboardRoutingModule, DashboardComponent, PanelDashboardComponent],
})
export class DashboardModule {}
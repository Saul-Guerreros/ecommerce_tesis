import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ReportesRoutingModule } from './reportes-routing.module';
import { ReportesComponent } from './reportes.component';

/** Componentes standalone: van en imports, no en declarations. */
@NgModule({
  declarations: [],
  imports: [CommonModule, ReportesRoutingModule, ReportesComponent],
})
export class ReportesModule {}
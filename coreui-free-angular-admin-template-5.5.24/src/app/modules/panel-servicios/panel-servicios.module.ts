import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PanelServiciosRoutingModule } from './panel-servicios-routing.module';
import { PanelServiciosComponent } from './panel-servicios.component';
import { PanelGeneralComponent } from './panel-general/panel-general.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, PanelServiciosRoutingModule, PanelServiciosComponent, PanelGeneralComponent],
})
export class PanelServiciosModule {}

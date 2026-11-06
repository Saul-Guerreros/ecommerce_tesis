import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CobrosServicioRoutingModule } from './cobros-servicio-routing.module';
import { CobrosServicioComponent } from './cobros-servicio.component';
import { ListCobroComponent } from './list-cobro/list-cobro.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, CobrosServicioRoutingModule, CobrosServicioComponent, ListCobroComponent],
})
export class CobrosServicioModule {}

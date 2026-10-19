import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { OrdenesTecnicoRoutingModule } from './ordenes-tecnico-routing.module';
import { OrdenesTecnicoComponent } from './ordenes-tecnico.component';
import { ListOrdenesComponent } from './list-ordenes/list-ordenes.component';
import { OrdenTrabajoComponent } from './orden-trabajo/orden-trabajo.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, OrdenesTecnicoRoutingModule, OrdenesTecnicoComponent, ListOrdenesComponent, OrdenTrabajoComponent],
})
export class OrdenesTecnicoModule {}

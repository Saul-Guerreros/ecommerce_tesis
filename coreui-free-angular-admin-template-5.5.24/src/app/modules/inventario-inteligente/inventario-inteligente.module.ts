// modules/inventario-inteligente/inventario-inteligente.module.ts
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { InventarioInteligenteRoutingModule } from './inventario-inteligente-routing.module';
import { InventarioInteligenteComponent } from './inventario-inteligente.component';
import { PanelInventarioComponent } from './panel-inventario/panel-inventario.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, InventarioInteligenteRoutingModule, InventarioInteligenteComponent, PanelInventarioComponent],
})
export class InventarioInteligenteModule {}

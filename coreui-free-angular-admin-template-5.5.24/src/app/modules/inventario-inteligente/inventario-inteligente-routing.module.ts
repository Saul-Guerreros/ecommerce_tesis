// modules/inventario-inteligente/inventario-inteligente-routing.module.ts
import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { InventarioInteligenteComponent } from './inventario-inteligente.component';
import { PanelInventarioComponent } from './panel-inventario/panel-inventario.component';

const routes: Routes = [
  { path: '', component: InventarioInteligenteComponent, children: [{ path: '', component: PanelInventarioComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class InventarioInteligenteRoutingModule {}

import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PanelServiciosComponent } from './panel-servicios.component';
import { PanelGeneralComponent } from './panel-general/panel-general.component';

const routes: Routes = [
  {
    path: '',
    component: PanelServiciosComponent,
    children: [{ path: '', component: PanelGeneralComponent, data: { title: 'Panel general de servicios' } }],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class PanelServiciosRoutingModule {}

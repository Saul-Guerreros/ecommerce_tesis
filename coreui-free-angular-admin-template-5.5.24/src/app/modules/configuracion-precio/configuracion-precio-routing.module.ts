import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ConfiguracionPrecioComponent } from './configuracion-precio.component';

const routes: Routes = [
  { path: '', component: ConfiguracionPrecioComponent },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ConfiguracionPrecioRoutingModule {}
import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ConfiguracionEnvioComponent } from './configuracion-envio.component';

const routes: Routes = [
  { path: '', component: ConfiguracionEnvioComponent },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ConfiguracionEnvioRoutingModule {}
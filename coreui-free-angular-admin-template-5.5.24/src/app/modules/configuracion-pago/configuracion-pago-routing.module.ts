import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ConfiguracionPagoComponent } from './configuracion-pago.component';

const routes: Routes = [
  { path: '', component: ConfiguracionPagoComponent },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ConfiguracionPagoRoutingModule {}
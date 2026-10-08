import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TarifasEnvioComponent } from './tarifas-envio.component';
import { ListTarifaEnvioComponent } from './list-tarifa-envio/list-tarifa-envio.component';

const routes: Routes = [
  {
    path: '',
    component: TarifasEnvioComponent,
    children: [{ path: '', component: ListTarifaEnvioComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class TarifasEnvioRoutingModule {}
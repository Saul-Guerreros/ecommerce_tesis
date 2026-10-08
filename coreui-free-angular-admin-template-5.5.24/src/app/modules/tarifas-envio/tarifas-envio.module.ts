import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TarifasEnvioRoutingModule } from './tarifas-envio-routing.module';
import { TarifasEnvioComponent } from './tarifas-envio.component';
import { ListTarifaEnvioComponent } from './list-tarifa-envio/list-tarifa-envio.component';
import { FormTarifaEnvioComponent } from './form-tarifa-envio/form-tarifa-envio.component';
import { DeleteTarifaEnvioComponent } from './delete-tarifa-envio/delete-tarifa-envio.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    TarifasEnvioRoutingModule,
    TarifasEnvioComponent,
    ListTarifaEnvioComponent,
    FormTarifaEnvioComponent,
    DeleteTarifaEnvioComponent,
  ],
})
export class TarifasEnvioModule {}
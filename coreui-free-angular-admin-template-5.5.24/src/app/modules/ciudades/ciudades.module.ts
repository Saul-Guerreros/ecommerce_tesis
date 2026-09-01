import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { CiudadesRoutingModule } from './ciudades-routing.module';
import { CiudadesComponent } from './ciudades.component';
import { ListCiudadComponent } from './list-ciudad/list-ciudad.component';
import { FormCiudadComponent } from './form-ciudad/form-ciudad.component';
import { DeleteCiudadComponent } from './delete-ciudad/delete-ciudad.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    CiudadesRoutingModule,
    CiudadesComponent,
    ListCiudadComponent,
    FormCiudadComponent,
    DeleteCiudadComponent,
  ],
})
export class CiudadesModule {}
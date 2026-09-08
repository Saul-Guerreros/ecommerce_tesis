import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { BarriosRoutingModule } from './barrios-routing.module';
import { BarriosComponent } from './barrios.component';
import { ListBarrioComponent } from './list-barrio/list-barrio.component';
import { FormBarrioComponent } from './form-barrio/form-barrio.component';
import { DeleteBarrioComponent } from './delete-barrio/delete-barrio.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    BarriosRoutingModule,
    BarriosComponent,
    ListBarrioComponent,
    FormBarrioComponent,
    DeleteBarrioComponent,
  ],
})
export class BarriosModule {}

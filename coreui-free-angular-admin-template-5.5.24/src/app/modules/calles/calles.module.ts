import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { CallesRoutingModule } from './calles-routing.module';
import { CallesComponent } from './calles.component';
import { ListCalleComponent } from './list-calle/list-calle.component';
import { FormCalleComponent } from './form-calle/form-calle.component';
import { DeleteCalleComponent } from './delete-calle/delete-calle.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    CallesRoutingModule,
    CallesComponent,
    ListCalleComponent,
    FormCalleComponent,
    DeleteCalleComponent,
  ],
})
export class CallesModule {}

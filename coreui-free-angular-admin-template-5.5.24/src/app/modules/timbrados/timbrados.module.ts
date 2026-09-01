import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TimbradosRoutingModule } from './timbrados-routing.module';
import { TimbradosComponent } from './timbrados.component';
import { ListTimbradoComponent } from './list-timbrado/list-timbrado.component';
import { FormTimbradoComponent } from './form-timbrado/form-timbrado.component';
import { DeleteTimbradoComponent } from './delete-timbrado/delete-timbrado.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    TimbradosRoutingModule,
    TimbradosComponent,
    ListTimbradoComponent,
    FormTimbradoComponent,
    DeleteTimbradoComponent,
  ],
})
export class TimbradosModule {}

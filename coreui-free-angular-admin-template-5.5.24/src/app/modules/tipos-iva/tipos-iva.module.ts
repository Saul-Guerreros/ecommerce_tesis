import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TiposIvaRoutingModule } from './tipos-iva-routing.module';
import { TiposIvaComponent } from './tipos-iva.component';
import { ListTipoIvaComponent } from './list-tipo-iva/list-tipo-iva.component';
import { FormTipoIvaComponent } from './form-tipo-iva/form-tipo-iva.component';
import { DeleteTipoIvaComponent } from './delete-tipo-iva/delete-tipo-iva.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    TiposIvaRoutingModule,
    TiposIvaComponent,
    ListTipoIvaComponent,
    FormTipoIvaComponent,
    DeleteTipoIvaComponent,
  ],
})
export class TiposIvaModule {}

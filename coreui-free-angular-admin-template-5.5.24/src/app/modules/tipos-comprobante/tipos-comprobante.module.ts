import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TiposComprobanteRoutingModule } from './tipos-comprobante-routing.module';
import { TiposComprobanteComponent } from './tipos-comprobante.component';
import { ListTipoComprobanteComponent } from './list-tipo-comprobante/list-tipo-comprobante.component';
import { FormTipoComprobanteComponent } from './form-tipo-comprobante/form-tipo-comprobante.component';
import { DeleteTipoComprobanteComponent } from './delete-tipo-comprobante/delete-tipo-comprobante.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    TiposComprobanteRoutingModule,
    TiposComprobanteComponent,
    ListTipoComprobanteComponent,
    FormTipoComprobanteComponent,
    DeleteTipoComprobanteComponent,
  ],
})
export class TiposComprobanteModule {}
